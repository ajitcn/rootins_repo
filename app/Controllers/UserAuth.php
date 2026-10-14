<?php

namespace App\Controllers;

use App\Controllers\BaseController;

/**
 *------------------------------------------------------------------
 * For Admin, Doctor and Pharmacy Assistant only
 *---------------------------------------------------------------------.
 */
class UserAuth extends BaseController
{
    /**
     * Login Page.
     */
    public function index()
    {
        if (session('patient_authenticated')) {
            return redirect()->to(base_url('patient'));
        }

        if (session('isLoggedIn') && is_array(session('user'))) {
            $roles = session('roles') ?? [];
            $redirect = in_array('Admin', $roles, true) || in_array('Super Admin', $roles, true)
                ? 'admin'
                : (in_array('Doctor', $roles, true)
                    ? 'doctor'
                    : (in_array('Pharmacy Assistant', $roles, true) ? 'pharmacy' : null));

            if ($redirect !== null) {
                return redirect()->to(base_url($redirect));
            }
        }

        return view('auth/user_login');
    }

    /**
     * Authenticate User.
     */
    public function authenticate()
    {
        $rules = ['user_id' => ['label' => 'User ID', 'rules' => 'required'], 'user_password' => ['label' => 'Password', 'rules' => 'required']];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', 'Username and Password are required.');
        }
        $user_id = trim($this->request->getPost('user_id'));
        $password = $this->request->getPost('user_password');
        $auth = service('auth');

        if (!$auth->attempt($this->request->getPost('user_id'), $this->request->getPost('user_password'))) {
            return redirect()->back()->withInput()->with('error', $auth->getError());
        }

        return redirect()->to($auth->redirectUrl());
    }

    /**
     * Logout.
     */
    public function logout()
    {
        session()->destroy();

        return redirect()->to(base_url('system/login'));
    }
}
