<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author\Merge;

use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;

/**
 * The merge CSV goes to product and comes back edited in Excel: UTF-8 with a BOM, `;` and Slovak column names.
 */
final class AuthorMergeCsv
{
    public const string GROUP = 'skupina';
    public const string VARIANT = 'variant';
    public const string EXT_SYSTEM = 'ext_system';
    public const string NAME_NORMALIZED = 'normalizovane_meno';
    public const string GROUP_IDENTIFIER = 'identifikator_skupiny';
    public const string GROUP_REVIEWED_COUNT = 'schvalenych_v_skupine';
    public const string AUTHOR_ID = 'author_id';
    public const string NAME = 'meno';
    public const string IDENTIFIER = 'identifikator';
    public const string REVIEWED = 'schvaleny';
    public const string ASSET_COUNT = 'fotiek';
    public const string CREATED_AT = 'zalozene';
    public const string CREATED_BY = 'zalozil';
    public const string CURRENT_AUTHOR_COUNT = 'ma_nadriadeneho';
    public const string CHILD_AUTHOR_COUNT = 'pocet_podriadenych';
    public const string PROPOSAL = 'navrh';
    public const string CURRENT_AUTHOR_IDS = 'nadriadeny_id';
    public const string CURRENT_AUTHOR_NAMES = 'nadriadeny_meno';
    public const string REASON = 'dovod';
    public const string DECISION = 'rozhodnutie';

    public const array COLUMNS = [
        self::GROUP,
        self::VARIANT,
        self::EXT_SYSTEM,
        self::NAME_NORMALIZED,
        self::GROUP_IDENTIFIER,
        self::GROUP_REVIEWED_COUNT,
        self::AUTHOR_ID,
        self::NAME,
        self::IDENTIFIER,
        self::REVIEWED,
        self::ASSET_COUNT,
        self::CREATED_AT,
        self::CREATED_BY,
        self::CURRENT_AUTHOR_COUNT,
        self::CHILD_AUTHOR_COUNT,
        self::PROPOSAL,
        self::CURRENT_AUTHOR_IDS,
        self::CURRENT_AUTHOR_NAMES,
        self::REASON,
        self::DECISION,
    ];

    /**
     * Columns the merge reads back; the others are only for the people who decide.
     */
    private const array REQUIRED_COLUMNS = [
        self::GROUP,
        self::EXT_SYSTEM,
        self::AUTHOR_ID,
        self::NAME,
        self::IDENTIFIER,
        self::CURRENT_AUTHOR_COUNT,
        self::DECISION,
    ];

    private const string DELIMITER = ';';
    private const string ENCLOSURE = '"';
    private const string ESCAPE = '';
    private const string BOM = "\u{FEFF}";

    /**
     * @param iterable<list<string|int>> $rows values in the order of COLUMNS
     */
    public static function write(string $path, iterable $rows): void
    {
        $handle = fopen($path, 'wb');
        if (false === $handle) {
            throw new InvalidArgumentException(sprintf('Cannot write the file "%s"', $path));
        }

        try {
            fwrite($handle, self::BOM);
            fputcsv($handle, self::COLUMNS, self::DELIMITER, self::ENCLOSURE, self::ESCAPE);
            foreach ($rows as $row) {
                fputcsv($handle, $row, self::DELIMITER, self::ENCLOSURE, self::ESCAPE);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array<int, array<string, string>> rows by their number (the header is 1), values by column name
     *
     * @throws InvalidArgumentException
     */
    public static function read(string $path): array
    {
        $content = is_file($path) ? file_get_contents($path) : false;
        if (false === $content) {
            throw new InvalidArgumentException(sprintf('Cannot read the file "%s"', $path));
        }
        if (false === mb_check_encoding($content, 'UTF-8')) {
            throw new InvalidArgumentException('The file is not UTF-8, save it as "CSV UTF-8"');
        }

        $handle = fopen('php://memory', 'w+b');
        if (false === $handle) {
            throw new InvalidArgumentException('Cannot open memory stream');
        }

        try {
            fwrite($handle, str_starts_with($content, self::BOM) ? substr($content, strlen(self::BOM)) : $content);
            rewind($handle);

            return self::readRows($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     *
     * @return array<int, array<string, string>>
     */
    private static function readRows($handle): array
    {
        $header = fgetcsv($handle, null, self::DELIMITER, self::ENCLOSURE, self::ESCAPE);
        $header = false === $header ? [] : array_map(static fn (?string $column): string => trim((string) $column), $header);
        $missing = array_diff(self::REQUIRED_COLUMNS, $header);
        if (false === empty($missing)) {
            throw new InvalidArgumentException(sprintf('Missing columns: %s', implode(', ', $missing)));
        }

        $rows = [];
        $rowNumber = 1;
        while (false !== ($values = fgetcsv($handle, null, self::DELIMITER, self::ENCLOSURE, self::ESCAPE))) {
            ++$rowNumber;
            if ([null] === $values) {
                continue;
            }
            if (count($values) !== count($header)) {
                throw new InvalidArgumentException(sprintf('Row %d has %d columns, the header has %d', $rowNumber, count($values), count($header)));
            }
            $rows[$rowNumber] = array_combine($header, array_map(static fn (?string $value): string => (string) $value, $values));
        }

        return $rows;
    }
}
