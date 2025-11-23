<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Console\NineVerse;

class HomeController extends Controller
{
    public function index()
    {
        $data = [
            'name' => NineVerse::NAME,
            'version' => NineVerse::VERSION,
            'v_fany_cli' => NineVerse::FANY_CLI,
            'v_nixs_te' => NineVerse::NIXS_TE,
            'codename' => NineVerse::CODENAME,
            'release_year' => NineVerse::RELEASE_YEAR,
            'release_status' => NineVerse::RELEASE_STATUS,
            'repo_url' => NineVerse::REPO_URL,
            'base_domain' => NineVerse::BASE_DOMAIN,
        ];
        $this->view('home', [
            'title' => 'Home',
            'data' => $data
        ]);
    }
}
