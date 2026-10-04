<?php

namespace App\Controllers;

use Core\Foundation\Controller;
use Core\Foundation\Http\Request;

class FixtureController extends Controller
{
    protected function initializeController()
    {
        $this->middleware('FixtureBlock', ['only' => ['guarded']]);
    }

    public function open()
    {
        return 'OPEN';
    }

    public function guarded()
    {
        return 'GUARDED-REACHED';
    }

    public function typed(int $page = 5)
    {
        return 'page=' . var_export($page, true);
    }

    public function byName($b, $a)
    {
        return "a={$a} b={$b}";
    }

    public function data()
    {
        return ['ok' => true, 'items' => [1, 2]];
    }

    public function store()
    {
        $data = $this->validate(['name' => 'required|min:3', 'age' => 'required|integer|between:18,99']);
        return ['validated' => $data];
    }

    public function needsAuthorize()
    {
        $this->authorize('anything');
        return 'AUTHORIZED';
    }

    public function showPage()
    {
        $this->with('name', 'Ctrl');
        parent::view('page', ['name' => 'FromController']);
    }

    protected function hidden()
    {
        return 'SHOULD-NOT-BE-ROUTABLE';
    }
}
