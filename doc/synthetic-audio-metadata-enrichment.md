# Synthetic Audio — obohatenie metadát assetu (ticket #84735)

## Kontext

Pri zakladaní TTS / synthetic-audio assetu z CMS sa dnes prenáša len
`text + voiceFamilySlug + podcasts + title + extResourceName/extId + assetLicence`.
Výsledný asset dostane vyplnený iba `displayTitle`. Produkt požaduje, aby asset dostal aj:

- **Authors** — namatchovaní podľa autorov článku (CMS posiela všetkých).
- **Title** — titulok článku (už funguje).
- **Description** — ak vieme prvý odsek textu, inak Perex. → **rozhodnutie: Perex.**
- **Keywords** — z článku, plus automaticky fixný `"podcast syntetické audio"`, plus per-hlas tag
  (keď CMS vyberie konkrétny hlas z DAMka, asset zdedí keywords nadefinované na tom hlase).

Cieľ: doplniť chýbajúce metadáta zo strany CMS a otvoriť per-voice taggovanie na strane DAMu.

---

## Analýza aktuálneho pokrytia

| Požiadavka | Stav dnes | Pokrytie |
|---|---|---|
| **Title** | CMS posiela `title`, `TtsAudioFactory.php:98` ho zapíše do `asset.texts.displayTitle`. | **~100 %** |
| **Per-voice keyword** | `VoiceFamily.keyword` (singular FK, `VoiceFamily.php:70`) sa **už dnes aplikuje na asset** cez `TtsRequestOrchestrator::syncFamilyKeyword()` (`TtsRequestOrchestrator.php:166-186`) — `asset.addKeyword()` + snapshot `voiceFamilyKeywordId` na `TtsAsset`. | **~60 %** (funguje, ale len 1 KW na hlas) |
| **Authors** | CMS vkladá mená autorov len do narratívneho `text`, neposiela ako metadáta. DTO nemá pole `authors`. **ALE** match infra na DAM strane existuje: `AuthorRepository::findIdsByNameAndExtSystem()` (`AuthorRepository.php:21`). | **~10 %** |
| **Description** | Vôbec sa neprenáša. V DAMku description nie je fixný stĺpec — žije v `AssetMetadata.customData` (viď nižšie). | **0 %** |
| **Keywords z článku** | CMS má `article.keywords` reláciu, neposiela. | **0 %** |
| **Auto keyword `"podcast syntetické audio"`** | Neexistuje. | **0 %** |

**Záver:** z 6 požiadaviek je 1 hotová (title), 1 čiastočne (single per-voice KW), zvyšok chýba.

---

## Ako DAM ukladá description / texty (dôležitá korekcia)

`AssetTexts` embed (`Entity/Embeds/AssetTexts.php`) má **iba `displayTitle`** — žiadne generické `description`.
Editovateľné texty (vrátane description) DAM drží v **`AssetMetadata.customData`** — JSON mapa kľúčovaná
podľa CustomForm element keys konkrétneho asset-typu (`Entity/AssetMetadata.php:36`,
`addCustomDataValue($key, $value)`). `keywordSuggestions` / `authorSuggestions` sú len návrhy, nie reálne priradenia.

Kanonický mapping „zdroj → asset/cieľové polia" je v **distribučnom flow** (toto je vzor, ktorý replikujeme):

- `DistributionBodyBuilder::setWriterProperties()` (`Domain/Distribution/DistributionBodyBuilder.php:48-56`)
  zoberie z ext-system konfigurácie `getMetadataMap()` a zavolá
- `AssetTextsWriter::writeValues($from, $to, $metadataMap)` (`Domain/Asset/AssetTextsWriter.php:21`),
  ktorý cez `PropertyAccessor` prepíše hodnoty podľa `TextsWriterConfiguration`
  (source/destination property path + normalizers).
- `metadataMap` je nakonfigurovaná per ext-system/asset-type
  (`Model/Configuration/ExtSystemAssetTypeDistributionRequirementConfiguration.php:27,50`).

→ **Description sa NEpridáva ako nový stĺpec.** Zapíše sa do `asset.getMetadata().customData[<descriptionKey>]`
cez existujúci `AssetMetadataManager` (`Domain/AssetMetadata/AssetMetadataManager.php`), kde `<descriptionKey>` je
element key audio CustomFormu. Mapping incoming polí → customData paths spravíme rovnakým vzorom ako distribúcia
(reuse `AssetTextsWriter` + per-ext-system TTS `metadataMap`).

---

## Rozhodnutia (odsúhlasené)

1. **Per-voice keywords:** pridať `VoiceFamily.keywords` (M:N) **paralelne** ku existujúcemu `keyword` (BC-safe; singular sa môže neskôr deprecovať).
2. **Auto keyword:** konfigurovateľný per ext-system → `ExtSystemTtsSettings.autoKeywordId`.
3. **Description source:** **Perex** (`article.texts.perex`).
4. **Authors mapping:** CMS posiela display names; DAM robí best-effort match cez `AuthorRepository::findIdsByNameAndExtSystem()` v scope ext-systemu, miss = silent skip.

---

## Dátový tok (po zmene)

```
CMS (publish článku)                      DAM
ArticleTtsAudioNarration...Executor
  └─ ArticleTtsMetadataMapper ──┐
       title  = headline         │
       desc   = perex            │   POST /api/sys/v1/audio/narration
       kws[]  = article.keywords │ ─────────────────────────────────►  TtsSynthesizeRequestDto (+desc,+kws,+authors)
       auth[] = author names     │                                         │ DispatchNewAudioNarration::buildInitialRequest
  DamTtsApiClient ───────────────┘                                         ▼
                                                              TtsNarrationRequest (persist +desc,+kws,+authors)
                                                                            │ async worker
                                                                            ▼
                                                              TtsRequestOrchestrator::processInitial
                                                                ├─ TtsAudioFactory: displayTitle (ako dnes)
                                                                ├─ writeDescription → metadata.customData[descKey]
                                                                └─ syncAssetMetadata (rozšírený syncFamilyKeyword):
                                                                     keywords = request.kws ∪ voiceFamily.keywords
                                                                                 ∪ ttsSettings.autoKeyword
                                                                     authors  = match(request.authors)
```

Pozn.: syntéza je **async** cez `TtsNarrationRequest` entitu — nové polia musia byť persistnuté na requeste
(`TtsNarrationRequest.php`, mirror `podcastIds` JSON / rozšírenie `TtsNarrationRequestSource` embedu),
nie len na DTO.

---

## Zmeny per repo

### 1) core-dam-bundle (centrálna business logika)

**DTO (jeden, zdieľaný Adm + Sys controllerom):**
- `src/Model/Dto/Tts/Audio/TtsSynthesizeRequestDto.php` — pridať:
  - `?string $description` (Length max napr. 5_000)
  - `array $keywords` (string[], názvy/slugy)
  - `array $authors` (string[], display names)

**Request entita + embed (persist async):**
- `src/Entity/TtsNarrationRequest.php` — pridať `description`, `keywords` (JSON), `authors` (JSON);
  alternatívne ich zložiť do nového embedu `TtsNarrationRequestMeta` vedľa `TtsNarrationRequestSource`.
- `src/Domain/Tts/Command/DispatchNewAudioNarration.php:134` (`buildInitialRequest`) — namapovať nové polia z DTO.

**Creation input:**
- `src/Model/Dto/Tts/Audio/TtsAudioCreationInput.php` — pridať `description`, `keywords[]`, `authors[]`;
  `forInitialRequest()` + `forStagingSwap()` ich čítajú z requestu.

**Factory + orchestrator (aplikácia na asset):**
- `src/Domain/Tts/Pipeline/TtsAudioFactory.php` — po `setDisplayTitle()` zapísať **description do customData**
  cez `AssetMetadataManager` / `AssetTextsWriter` + TTS `metadataMap` (descriptionKey z audio CustomFormu).
- `src/Domain/Tts/Pipeline/TtsRequestOrchestrator.php:166` — rozšíriť `syncFamilyKeyword()` →
  `syncAssetMetadata()`:
  - keywords = union(request.keywords resolvnuté na `Keyword` + `voiceFamily.getKeywords()` (nové M:N)
    + `extSystem.ttsSettings.autoKeyword`); findOrCreate `Keyword` v scope extSystem; `asset.addKeyword()`.
  - authors = pre každé meno `AuthorRepository::findIdsByNameAndExtSystem()` → `asset.addAuthor()`.
  - **snapshot pre regen-diff** treba rozšíriť z `TtsAsset.voiceFamilyKeywordId` (single) na zoznam ID
    (napr. `voiceFamilyKeywordIds` JSON), aby reconcile pri regenerácii fungoval pre viac keywords.

**Per-voice keywords (entita):**
- `src/Entity/VoiceFamily.php` — pridať `Collection<Keyword> $keywords` (M:N, `EqualExtSystem`, EntityIdHandler), gettery/settery; `keyword` ostáva.

**Ext-system TTS settings:**
- `src/Entity/Embeds/ExtSystemTtsSettings.php` — pridať `?string $autoKeywordId` (GUID, nullable) + accessor.
- `ExtSystemFacade` — zahrnúť do existujúceho ext-system CRUD (TTS settings sú už tam foldnuté).

### 2) core-dam (iba migrácie)
Lokácia: `/home/work/projects/anzu/core-dam/src/Migrations`
- pivot tabuľka `voice_family_keyword` (M:N VoiceFamily↔Keyword).
- `tts_narration_request`: stĺpce `description`, `keywords` (json), `authors` (json) — podľa zvolenej embed štruktúry.
- `ext_system` (TTS settings embed): stĺpec `..._auto_keyword_id`.
- `tts_asset`: `voice_family_keyword_ids` (json) ak pôjdeme cestou multi-snapshot.

### 3) core-cms (backend)
*(cesty z prieskumu — pri implementácii overiť)*
- `src/Domain/AudioNarration/Model/DataObject/Sys/TtsDispatchRequestDto.php` — pridať `description`, `keywords[]`, `authors[]`.
- `src/Domain/AudioNarration/HttpClient/DamTtsApiClient.php` — serializovať nové polia.
- Nový `ArticleTtsMetadataMapper` (alebo rozšíriť `ArticleTtsTextBuilder.php`):
  - `description` = `article.texts.perex`
  - `keywords[]` = `article.keywords` → názvy
  - `authors[]` = `article.authors` → display names
- `src/Domain/Scenario/Executor/ArticleTtsAudioNarrationTaskExecutor.php:45` — zavolať mapper, naplniť request DTO.
- Voice resolver / eligibility checker = **bez zmeny**.

### 4) admin-cms (frontend)
**Bez zmeny** — feature je serverovo orchestrovaná pri publish-e; voľba hlasu cez existujúce polia.

### 5) admin-dam (frontend)
- `VoiceFamilyEditForm.vue` — pridať **`keywords` multi-autocomplete** (Keyword IDs) popri existujúcom singular `keyword`.
- ExtSystem TTS settings tab — pridať selector **auto keyword** (`autoKeywordId`).
- `AssetDetailSidebarTts.vue` — voliteľné: zobraziť aplikované keywords/authors (inak žijú v štandardnom Asset paneli).
- Types: doplniť nové polia do VoiceFamily / ExtSystem TTS settings rozhraní.

---

## Reuse (nepísať nanovo)
- `AuthorRepository::findIdsByNameAndExtSystem()` — author name match (`AuthorRepository.php:21`).
- `AssetTextsWriter::writeValues()` + `TextsWriterConfiguration` — mapping zdroj→customData (`Domain/Asset/AssetTextsWriter.php`).
- `AssetMetadataManager` — zápis `customData` na asset.
- `Asset::addKeyword()/addAuthor()/removeKeywordById()/getMetadata()` (`Entity/Asset.php:251,266,271,310`).
- `TtsRequestOrchestrator::syncFamilyKeyword()` — vzor pre keyword reconcile (rozšíriť, nie duplikovať).

---

## Otvorený implementačný detail
**Description element key:** treba určiť pod akým `customData` kľúčom žije „description" pre audio asset
(z CustomFormu daného ext-systemu). Odporúčanie: definovať TTS `metadataMap` v ext-system konfigurácii
(rovnako ako distribučné `metadataMap`), aby bol mapping incoming polí → customData paths konfigurovateľný,
nie hardcoded. Pri implementácii overiť aktuálny audio CustomForm v cieľovom ext-systeme.

---

## Verifikácia (end-to-end)
1. V `core-cms` vytvor článok s autormi, keywords a perexom → publish → spustí sa TTS scenár.
2. V DAM over na výslednom TtsAsset / audio assete:
   - `displayTitle` = headline,
   - description (customData) = perex,
   - keywords obsahujú: článkové KW + voice-family KW + `"podcast syntetické audio"`,
   - authors = namatchovaní autori (alebo prázdne pri miss).
3. V admin-dam pri VoiceFamily edit-e priraď ≥2 keywords + nastav auto keyword v ext-system TTS settings →
   znovu publish → over že všetky pribudli na asset.
4. **Regenerácia**: zmeň voice-family keywords a spusti regen → over že reconcile pridá nové a odstráni staré
   (kontrola multi-snapshot diffu, nie len single `voiceFamilyKeywordId`).
5. Unit testy `TtsAudioFactory` / `syncAssetMetadata` pre keyword union + author match + customData zápis.

---

## Návrh dávkovania (batches)
- **B1 (core-dam-bundle + core-dam):** entity (VoiceFamily.keywords, ExtSystemTtsSettings.autoKeywordId,
  TtsNarrationRequest polia) + migrácie.
- **B2 (core-dam-bundle):** DTO → request → input → factory/orchestrator (`syncAssetMetadata`, description do customData).
- **B3 (core-cms):** mapper + DTO + DamTtsApiClient + executor wiring.
- **B4 (admin-dam):** VoiceFamily keywords multi-autocomplete + ext-system auto keyword UI + types.
- **B5:** end-to-end verifikácia + testy.
