<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParseAiTimeEntryRequest;
use App\Services\Ai\AiTimeEntryParserService;

class AiTimeEntryParserController extends Controller
{
    public function store(ParseAiTimeEntryRequest $request, AiTimeEntryParserService $parser): array
    {
        return $parser->parse(
            $request->validated('text'),
            $request->validated('company_id')
        );
    }
}
