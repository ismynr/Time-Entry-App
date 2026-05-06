<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;

class CompanyController extends Controller
{
    public function index()
    {
        return Company::query()->orderBy('name')->get(['id', 'name']);
    }
}
