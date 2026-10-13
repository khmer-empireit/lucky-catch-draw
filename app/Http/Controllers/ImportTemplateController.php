<?php

namespace App\Http\Controllers;

use App\Services\Import\TemplateGenerator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportTemplateController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        return TemplateGenerator::download();
    }
}
