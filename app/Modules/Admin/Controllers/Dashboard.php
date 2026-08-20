<?php namespace Admin\Controllers;

use App\Controllers\BaseController;

class Dashboard extends BaseController	
{
	public function index()
	{
		return view(ADMIN_VIEW.'pages/dashboard');
	}
}