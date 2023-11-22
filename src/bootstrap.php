<?php


namespace Brix;





use Brix\Core\BrixEnvFactorySingleton;
use Brix\Core\Type\BrixEnv;
use Brix\MailSpool\Mailspool;
use Brix\MailSpool\MailSpoolFacet;
use Brix\Tax\Tax;
use Brix\Tax\Type\T_TaxConfig;
use Phore\Cli\CliDispatcher;

CliDispatcher::addClass(Tax::class);



