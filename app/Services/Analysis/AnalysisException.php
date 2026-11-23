<?php

namespace App\Services\Analysis;

use RuntimeException;

/** An analysis cannot run (e.g. no imported sales in the period). The message is shown to the user. */
class AnalysisException extends RuntimeException
{
}
