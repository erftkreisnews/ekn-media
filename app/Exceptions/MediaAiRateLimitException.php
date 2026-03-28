<?php

namespace App\Exceptions;

use Exception;

/** Wird bei OpenAI 429 oder App-Rate-Limit geworfen; Job soll mit Backoff erneut versuchen. */
class MediaAiRateLimitException extends Exception {}
