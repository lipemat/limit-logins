<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Service_Providers;

interface Provider {
	public function register(): void;
}
