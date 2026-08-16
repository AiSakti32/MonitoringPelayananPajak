<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuthService;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Session::isLoggedIn()) {
            redirect('/dashboard');
        }

        $this->render('auth/login', [
            'pageTitle' => 'Login',
            'error' => get_flash('error'),
            'success' => get_flash('success'),
            'errors' => get_flash('errors', []),
            'loginCode' => get_flash('login_code'),
        ], 'layouts/auth');
    }

    public function login(): void
    {
        if (Session::isLoggedIn()) {
            redirect('/dashboard');
        }

        $username = (string) Request::input('username', '');
        $password = (string) Request::input('password', '');
        $remember = Request::input('remember') === '1';

        $result = (new AuthService())->attempt($username, $password, $remember);
        if (!$result['ok']) {
            Session::flash('error', $result['message'] ?? 'Login gagal. Periksa username dan password.');
            Session::flash('login_code', $result['code'] ?? 'failed');
            if (!empty($result['errors'])) {
                Session::flash('errors', $result['errors']);
            }
            Session::flash('old', ['username' => $username, 'remember' => $remember ? '1' : '']);
            redirect('/login');
        }

        redirect('/dashboard');
    }

    public function logout(): void
    {
        (new AuthService())->logout();

        // Session was destroyed; start a fresh session for one-time flash message.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            Session::start((array) config('app.session', []));
        }
        Session::flash('success', 'Anda telah keluar dari sistem.');
        redirect('/login');
    }
}
