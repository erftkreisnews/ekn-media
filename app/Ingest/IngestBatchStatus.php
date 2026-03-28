<?php

namespace App\Ingest;

final class IngestBatchStatus
{
    public const QUEUED_FOR_RENDER = 'queued_for_render';

    public const RENDERING = 'rendering';

    public const UPLOADING_TO_S3 = 'uploading_to_s3';

    public const ATTACHED_TO_NEWS = 'attached_to_news';

    public const FAILED = 'failed';
}
