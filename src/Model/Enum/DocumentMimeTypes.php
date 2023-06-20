<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum DocumentMimeTypes: string implements EnumInterface
{
    use BaseEnumTrait;

    public const CHOICES = [
        self::MIME_PDF,
        self::TEXT_PLAIN,
        self::MIME_X_ABIWORD,
        self::AMAZON_EBOOK,
        self::TEXT_CSV,
        self::MIME_MSWORD,
        self::MIME_OPENXML,
        self::MIME_EPUB,
        self::TEXT_HTML,
        self::TEXT_CALENDAR,
        self::MOBILE_EBOOK,
        self::OPEN_DOCUMENT_PRESENTATION,
        self::OPEN_DOCUMENT_SHEET,
        self::OPEN_DOCUMENT_TEXT,
        self::POWER_POINT,
        self::POWER_POINT_OPEN_XML,
        self::RTF,
        self::MICROSOFT_VISIO,
        self::MICROSOFT_EXCEL,
        self::MICROSOFT_OPEN_XML,
        self::TEXT_XML,
        self::APPLICATION_XML,
    ];

    private const MIME_PDF = 'application/pdf';
    private const TEXT_PLAIN = 'text/plain';
    private const MIME_X_ABIWORD = 'application/x-abiword';
    private const AMAZON_EBOOK = 'application/vnd.amazon.ebook';
    private const TEXT_CSV = 'text/csv';
    private const MIME_MSWORD = 'application/msword';
    private const MIME_OPENXML = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    private const MIME_EPUB = 'application/epub+zip';
    private const TEXT_HTML = 'text/html';
    private const TEXT_CALENDAR = 'text/calendar';
    private const MOBILE_EBOOK = 'application/x-mobipocket-ebook';
    private const OPEN_DOCUMENT_PRESENTATION = 'application/vnd.oasis.opendocument.presentation';
    private const OPEN_DOCUMENT_SHEET = 'application/vnd.oasis.opendocument.spreadsheet';
    private const OPEN_DOCUMENT_TEXT = 'application/vnd.oasis.opendocument.text';
    private const POWER_POINT = 'application/vnd.ms-powerpoint';
    private const POWER_POINT_OPEN_XML = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
    private const RTF = 'application/rtf';
    private const MICROSOFT_VISIO = 'application/vnd.visio';
    private const MICROSOFT_EXCEL = 'application/vnd.ms-excel';
    private const MICROSOFT_OPEN_XML = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    private const TEXT_XML = 'text/xml';
    private const APPLICATION_XML = 'application/xml';

    case MimePdf = self::MIME_PDF;
    case TextPlain = self::TEXT_PLAIN;
    case MimeXAbiWord = self::MIME_X_ABIWORD;
    case AmazonEbook = self::AMAZON_EBOOK;
    case TextCsv = self::TEXT_CSV;
    case MimeMSWord = self::MIME_MSWORD;
    case MimeOpenXml = self::MIME_OPENXML;
    case MimeEpub = self::MIME_EPUB;
    case TextHtml = self::TEXT_HTML;
    case TextCalendar = self::TEXT_CALENDAR;
    case MobileEbook = self::MOBILE_EBOOK;
    case OpenDocumentPresentation = self::OPEN_DOCUMENT_PRESENTATION;
    case OpenDocumentSheet = self::OPEN_DOCUMENT_SHEET;
    case OpenDocumentText = self::OPEN_DOCUMENT_TEXT;
    case PowerPoint = self::POWER_POINT;
    case PowerPointOpenXml = self::POWER_POINT_OPEN_XML;
    case Rtf = self::RTF;
    case MicrosoftVisio = self::MICROSOFT_VISIO;
    case MicrosoftExcel = self::MICROSOFT_EXCEL;
    case MicrosoftOpenXml = self::MICROSOFT_OPEN_XML;
    case TextXml = self::TEXT_XML;
    case ApplicationXml = self::APPLICATION_XML;
}
