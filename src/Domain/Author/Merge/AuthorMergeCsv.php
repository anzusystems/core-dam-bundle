<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author\Merge;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;

/**
 * The merge CSV goes to product and comes back edited in Excel: UTF-8 with a BOM, `;` and Slovak column names.
 */
final class AuthorMergeCsv
{
    public const string GROUP = 'group';
    public const string CASE = 'case';
    public const string EXT_SYSTEM = 'ext_system';
    public const string NAME_NORMALIZED = 'name_normalized';
    public const string GROUP_IDENTIFIER = 'group_identifier';
    public const string GROUP_REVIEWED_COUNT = 'group_reviewed_count';
    public const string AUTHOR_ID = 'author_id';
    public const string NAME = 'name';
    public const string IDENTIFIER = 'identifier';
    public const string REVIEWED = 'reviewed';
    public const string ASSET_COUNT = 'asset_count';
    public const string CREATED_AT = 'created_at';
    public const string CREATED_BY = 'created_by';
    public const string CURRENT_AUTHOR_COUNT = 'current_author_count';
    public const string CHILD_AUTHOR_COUNT = 'child_author_count';
    public const string PROPOSAL = 'proposal';
    public const string TARGET_IDS = 'target_ids';
    public const string TARGET_NAMES = 'target_names';
    public const string REASON = 'reason';
    public const string DECISION = 'decision';

    public const array COLUMNS = [
        self::GROUP,
        self::CASE,
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
        self::TARGET_IDS,
        self::TARGET_NAMES,
        self::REASON,
        self::DECISION,
    ];

    /**
     * Columns the merge reads back; the others are only for the people who decide.
     */
    private const array REQUIRED_COLUMNS = [
        self::GROUP,
        self::EXT_SYSTEM,
        self::NAME_NORMALIZED,
        self::GROUP_IDENTIFIER,
        self::AUTHOR_ID,
        self::NAME,
        self::IDENTIFIER,
        self::TARGET_IDS,
        self::DECISION,
    ];

    private const string LIST_SEPARATOR = ' + ';
    private const string DELIMITER = ';';
    private const string ENCLOSURE = '"';
    private const string ESCAPE = '';
    private const string BOM = "\u{FEFF}";

    /**
     * @param list<string> $values
     */
    public static function joinList(array $values): string
    {
        return implode(self::LIST_SEPARATOR, $values);
    }

    /**
     * @return list<string> values of a cell written by joinList(), people may leave out the spaces
     */
    public static function splitList(string $cell): array
    {
        return array_values(array_filter(
            array_map(trim(...), explode(trim(self::LIST_SEPARATOR), $cell)),
            static fn (string $value): bool => App::EMPTY_STRING !== $value,
        ));
    }

    /**
     * Never overwrites a file, so a wrong path cannot destroy a CSV people already checked.
     *
     * @param iterable<list<string|int>> $rows values in the order of COLUMNS
     *
     * @throws InvalidArgumentException
     */
    public static function write(string $path, iterable $rows): void
    {
        if (file_exists($path)) {
            throw new InvalidArgumentException(sprintf('The file "%s" already exists', $path));
        }
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
