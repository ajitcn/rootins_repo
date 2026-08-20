<?php

namespace App\Controllers;

use App\Controllers\BaseController;
 
/**
 *------------------------------------------------------------------
 * For Admin,Doctor and Farmassist only
 *---------------------------------------------------------------------
 */

class UserAuth extends BaseController
{
    /**
     * Login Page
     */
    public function index()
    {
        // Already logged in?
        if (session()->has('user')) {
            
        }

        return view('auth/user_login');
    }

    /**
     * Authenticate User
     */
    public function authenticate()
    {
        $rules = [
            'user_id' => [
                'label' => 'User ID',
                'rules' => 'required',
            ],
            'user_password' => [
                'label' => 'Password',
                'rules' => 'required',
            ],
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Username and Password are required.');
        }

        $user_id = trim($this->request->getPost('user_id'));
        $password = $this->request->getPost('user_password');


        $auth = service('auth');

        if (! $auth->attempt(
            $this->request->getPost('user_id'),
            $this->request->getPost('user_password')
        )) {
            return redirect()->back()
                ->withInput()
                ->with('error', $auth->getError());
        }

        return redirect()->to($auth->redirectUrl());

 
         

         
    }

    /**
     * Logout
     */
    public function logout()
    {
        session()->destroy();

        return redirect()->to('/login');
    }

     
}