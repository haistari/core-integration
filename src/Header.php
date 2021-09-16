<?php

namespace Haistar;

class Header
{
	public function __construct()
    {
		return getallheaders();
	}
}