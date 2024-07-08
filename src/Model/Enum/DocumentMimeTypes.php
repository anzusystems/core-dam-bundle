<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum DocumentMimeTypes: string implements EnumInterface
{
    use BaseEnumTrait;

    public const array CHOICES = [
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
        self::SVG,
    ];

    private const string MIME_PDF = 'application/pdf';
    private const string TEXT_PLAIN = 'text/plain';
    private const string MIME_X_ABIWORD = 'application/x-abiword';
    private const string AMAZON_EBOOK = 'application/vnd.amazon.ebook';
    private const string TEXT_CSV = 'text/csv';
    private const string SVG = 'image/svg+xml';
    private const string MIME_MSWORD = 'application/msword';
    private const string MIME_OPENXML = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    private const string MIME_EPUB = 'application/epub+zip';
    private const string TEXT_HTML = 'text/html';
    private const string TEXT_CALENDAR = 'text/calendar';
    private const string MOBILE_EBOOK = 'application/x-mobipocket-ebook';
    private const string OPEN_DOCUMENT_PRESENTATION = 'application/vnd.oasis.opendocument.presentation';
    private const string OPEN_DOCUMENT_SHEET = 'application/vnd.oasis.opendocument.spreadsheet';
    private const string OPEN_DOCUMENT_TEXT = 'application/vnd.oasis.opendocument.text';
    private const string POWER_POINT = 'application/vnd.ms-powerpoint';
    private const string POWER_POINT_OPEN_XML = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
    private const string RTF = 'application/rtf';
    private const string MICROSOFT_VISIO = 'application/vnd.visio';
    private const string MICROSOFT_EXCEL = 'application/vnd.ms-excel';
    private const string MICROSOFT_OPEN_XML = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    private const string TEXT_XML = 'text/xml';
    private const string APPLICATION_XML = 'application/xml';

    case MimePdf = self::MIME_PDF;
    case TextPlain = self::TEXT_PLAIN;
    case MimeXAbiWord = self::MIME_X_ABIWORD;
    case AmazonEbook = self::AMAZON_EBOOK;
    case TextCsv = self::TEXT_CSV;
    case Svg = self::SVG;
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
