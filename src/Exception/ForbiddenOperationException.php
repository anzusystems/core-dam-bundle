<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use DomainException;

class ForbiddenOperationException extends DomainException
{
    public const ERROR_MESSAGE = 'forbidden_operation_error';
    public const DETAIL_INVALID_STATE_TRANSACTION = 'invalid_state_transaction';
    public const DETAIL_INVALID_ASSET_TYPE = 'invalid_asset_type';
    public const ASSET_SIZE_TOO_LARGE = 'asset_size_too_large';
    public const DETAIL_INVALID_ASSET_SLOT = 'invalid_asset_slot';
    public const DETAIL_BULK_SIZE_EXCEEDED = 'bulk_size_exceeded';
    public const CUSTOM_FORM_NOT_EXISTS = 'custom_form_not_exists';
    public const ASSET_NOT_FULLY_UPLOADED = 'asset_not_fully_uploaded';
    public const NOT_ALLOWED_DOWNLOAD = 'not_allowed_download';
    public const LICENCE_MISMATCH = 'licence_mismatch';
    public const ASSET_DELETE_DURING_REORDER = 'asset_file_delete_during_reorder';
    public const FILE_UPLOAD_TOO_MANY_FILES = 'file_upload_too_many_files';

    public function __construct(
        private readonly string $detail
    ) {
        parent::__construct(self::ERROR_MESSAGE);
    }

    public function getDetail(): string
    {
        return $this->detail;
    }
}
