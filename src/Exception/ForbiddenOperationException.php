<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use DomainException;

class ForbiddenOperationException extends DomainException
{
    public const string ERROR_MESSAGE = 'forbidden_operation_error';
    public const string DETAIL_INVALID_STATE_TRANSACTION = 'invalid_state_transaction';
    public const string DETAIL_INVALID_ASSET_TYPE = 'invalid_asset_type';
    public const string ASSET_SIZE_TOO_LARGE = 'asset_size_too_large';
    public const string DETAIL_INVALID_ASSET_SLOT = 'invalid_asset_slot';
    public const string DETAIL_BULK_SIZE_EXCEEDED = 'bulk_size_exceeded';
    public const string CUSTOM_FORM_NOT_EXISTS = 'custom_form_not_exists';
    public const string ASSET_NOT_FULLY_UPLOADED = 'asset_not_fully_uploaded';
    public const string NOT_ALLOWED_DOWNLOAD = 'not_allowed_download';
    public const string LICENCE_MISMATCH = 'licence_mismatch';
    public const string ASSET_DELETE_DURING_REORDER = 'asset_file_delete_during_reorder';
    public const string FILE_UPLOAD_TOO_MANY_FILES = 'file_upload_too_many_files';
    public const string FILE_IS_USED = 'file_is_used';

    public const string IS_BLOCKING_ERROR = 'distribution_is_blocking';

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
