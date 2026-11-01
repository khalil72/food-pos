<?php
declare(strict_types=1);

final class AuthController extends Controller
{
    public function login(): void
    {
        if (is_post()) {
            $username = (string)input('username', '');
            $password = (string)($_POST['password'] ?? '');
            if ($username === '' || $password === '') {
                flash('danger', 'Enter your username and password.');
                set_old(['username' => $username]);
                redirect('login');
            }
            $error = Auth::attempt($username, $password);
            if ($error) {
                flash('danger', $error);
                set_old(['username' => $username]);
                redirect('login');
            }
            redirect(Auth::homeRoute());
        }
        $this->view('auth/login', ['title' => 'Sign in', 'flashes' => flashes()], 'auth');
    }

    public function logout(): void
    {
        $this->requirePost();
        audit('logout', 'user', Auth::id());
        Auth::logout();
        flash('success', 'You have been signed out.');
        redirect('login');
    }
}
