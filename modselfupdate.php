<?php
/**
 *            DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
 *                  Version 2, December 2004
 *
 * Copyright (C) 2016 Michael Dekker <prestashop@michaeldekker.com>
 *
 * Everyone is permitted to copy and distribute verbatim or modified
 * copies of this license document, and changing it is allowed as long
 * as the name is changed.
 *
 *           DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
 * TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION
 *
 *  @author    Michael Dekker <prestashop@michaeldekker.com>
 *  @copyright 2016 Michael Dekker
 *  @license   http://www.wtfpl.net/about/ Do What The Fuck You Want To Public License (WTFPL v2)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once 'vendor/autoload.php';

/**
 * Class ModSelfUpdate
 */
class ModSelfUpdate extends Module
{
    const AUTO_UPDATE = 'MODSELFUPDATE_AUTO_UPDATE';
    const LAST_CHECK = 'MODSELFUPDATE_LAST_CHECK';
    const AUTHENTICATE = 'MODSELFUPDATE_AUTHENTICATE';
    const USERNAME_OR_TOKEN = 'MODSELFUPDATE_USERNAME';
    const PASSWORD = 'MODSELFUPDATE_PASSWORD';
    const CHECK_REPO = 'MODSELFUPDATE_CHECK_REPO';

    /** @var string $baseUrl Module base URL */
    public $baseUrl;

    /**
     * ModSelfUpdate constructor.
     */
    public function __construct()
    {
        $this->name = 'modselfupdate';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Michael Dekker';
        $this->bootstrap = true;

        parent::__construct();
        $this->displayName = $this->l('Self updating module');
        $this->description = $this->l('Uses GitHub in order to update automatically');

        $this->baseUrl = $this->context->link->getAdminLink('AdminModules', true).'&'.http_build_query(array(
                'configure' => $this->name,
                'tab_module' => $this->tab,
                'module_name' => $this->name,
            ));

        $this->checkUpdate();
    }

    /**
     * Install this module
     *
     * @return bool Whether this module was successfully installed
     * @throws PrestaShopException
     */
    public function install()
    {
        Configuration::updateGlobalValue(self::CHECK_REPO, 'firstred/modselfupdate');

        return parent::install();
    }

    /**
     * Uninstall this module
     *
     * @return bool Whether this module was successfully uninstalled
     * @throws PrestaShopException
     */
    public function uninstall()
    {
        Configuration::deleteByName('AUTO_UPDATE');
        Configuration::deleteByName('LAST_CHECK');
        Configuration::deleteByName('AUTHENTICATE');
        Configuration::deleteByName('USERNAME_OR_TOKEN');
        Configuration::deleteByName('PASSWORD');
        Configuration::deleteByName('CHECK_REPO');

        return parent::uninstall();
    }

    /**
     * Update configuration value in ALL contexts
     *
     * @param string $key    Configuration key
     * @param mixed  $values Configuration values, can be string or array with id_lang as key
     * @param bool   $html   Contains HTML
     */
    public function updateAllValue($key, $values, $html = false)
    {
        foreach (Shop::getShops() as $shop) {
            Configuration::updateValue($key, $values, $html, $shop['id_shop_group'], $shop['id_shop']);
        }
        Configuration::updateGlobalValue($key, $values, $html);
    }

    /**
     * Get the Shop ID of the current context
     * Retrieves the Shop ID from the cookie
     *
     * @return int Shop ID
     */
    public function getShopId()
    {
        $cookie = Context::getContext()->cookie->getFamily('shopContext');

        return (int) Tools::substr($cookie['shopContext'], 2, count($cookie['shopContext']));
    }

    /**
     * Get module configuration page
     *
     * @return string Configuration page HTML
     */
    public function getContent()
    {
        $output = '';

        $output .= $this->postProcess();

        return $output.$this->renderGeneralOptions();
    }

    /**
     * Render the General options form
     *
     * @return string HTML
     */
    protected function renderGeneralOptions()
    {
        $helper = new HelperOptions();
        $helper->id = 1;
        $helper->module = $this;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->title = $this->displayName;
        $helper->show_toolbar = false;

        return $helper->generateOptions(array_merge($this->getModuleOptions()));
    }

    /**
     * Get available general options
     *
     * @return array General options
     */
    protected function getModuleOptions()
    {
        return array(
            'module' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                'fields' => array(
                    self::AUTO_UPDATE => array(
                        'title' => $this->l('Auto update'),
                        'desc' => $this->l('Automatically update this module'),
                        'type' => 'bool',
                        'name' => self::AUTO_UPDATE,
                        'value' => Configuration::get(self::AUTO_UPDATE),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                    ),
                    self::AUTHENTICATE => array(
                        'title' => $this->l('Authenticate with GitHub'),
                        'desc' => $this->l('Authenticate with GitHub in order to increase the rate limit'),
                        'type' => 'bool',
                        'name' => self::AUTHENTICATE,
                        'value' => Configuration::get(self::AUTHENTICATE),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                    ),
                    self::USERNAME_OR_TOKEN => array(
                        'title' => $this->l('Username or Token'),
                        'type' => 'text',
                        'name' => self::USERNAME_OR_TOKEN,
                        'value' => Configuration::get(self::USERNAME_OR_TOKEN),
                        'validation' => 'isString',
                        'cast' => 'strval',
                    ),
                    self::PASSWORD => array(
                        'title' => $this->l('Password'),
                        'type' => 'text',
                        'name' => self::PASSWORD,
                        'value' => Configuration::get(self::PASSWORD),
                        'validation' => 'isString',
                        'cast' => 'strval',
                    ),
                    self::CHECK_REPO => array(
                        'title' => $this->l('Check this repository'),
                        'type' => 'text',
                        'name' => self::USERNAME_OR_TOKEN,
                        'value' => Configuration::get(self::USERNAME_OR_TOKEN),
                        'validation' => 'isString',
                        'cast' => 'strval',
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Process module settings
     *
     * @return string Error message
     */
    protected function postProcess()
    {
        $output = '';

        if (Tools::isSubmit('submitOptionsconfiguration')) {
            $output .= $this->postProcessGeneralOptions();
        }

        return $output;
    }

    /**
     * Process General Options
     */
    protected function postProcessGeneralOptions()
    {
        $validated = true;

        $autoUpdate = Tools::getValue(self::AUTO_UPDATE);
        $auth = Tools::getValue(self::AUTHENTICATE);
        $username = Tools::getValue(self::USERNAME_OR_TOKEN);
        $password = Tools::getValue(self::PASSWORD);
        $repo = Tools::getValue(self::CHECK_REPO);

        $validated &= $this->validateRepo($repo);

        if ($validated) {
            if (Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE')) {
                if (Shop::getContext() == Shop::CONTEXT_ALL) {
                    $this->updateAllValue(self::AUTO_UPDATE, $autoUpdate);
                    $this->updateAllValue(self::AUTHENTICATE, $auth);
                    $this->updateAllValue(self::USERNAME_OR_TOKEN, $username);
                    $this->updateAllValue(self::PASSWORD, $password);
                    $this->updateAllValue(self::CHECK_REPO, $repo);
                } elseif (is_array(Tools::getValue('multishopOverrideOption'))) {
                    $idShopGroup = (int) Shop::getGroupFromShop($this->getShopId(), true);
                    $multishopOverride = Tools::getValue('multishopOverrideOption');
                    if (Shop::getContext() == Shop::CONTEXT_GROUP) {
                        foreach (Shop::getShops(false, $this->getShopId()) as $idShop) {
                            if ($multishopOverride[self::AUTO_UPDATE]) {
                                Configuration::updateValue(self::AUTO_UPDATE, $autoUpdate, false, $idShopGroup, $idShop);
                            }
                            if ($multishopOverride[self::AUTHENTICATE]) {
                                Configuration::updateValue(self::AUTHENTICATE, $auth, false, $idShopGroup, $idShop);
                            }
                            if ($multishopOverride[self::USERNAME_OR_TOKEN]) {
                                Configuration::updateValue(self::USERNAME_OR_TOKEN, $username, false, $idShopGroup, $idShop);
                            }
                            if ($multishopOverride[self::PASSWORD]) {
                                Configuration::updateValue(self::PASSWORD, $password, false, $idShopGroup, $idShop);
                            }
                            if ($multishopOverride[self::CHECK_REPO]) {
                                Configuration::updateValue(self::CHECK_REPO, $repo, false, $idShopGroup, $idShop);
                            }
                        }
                    } else {
                        $idShop = (int) $this->getShopId();
                        if ($multishopOverride[self::AUTO_UPDATE]) {
                            Configuration::updateValue(self::AUTO_UPDATE, $autoUpdate, false, $idShopGroup, $idShop);
                        }
                        if ($multishopOverride[self::AUTHENTICATE]) {
                            Configuration::updateValue(self::AUTHENTICATE, $auth, false, $idShopGroup, $idShop);
                        }
                        if ($multishopOverride[self::USERNAME_OR_TOKEN]) {
                            Configuration::updateValue(self::USERNAME_OR_TOKEN, $username, false, $idShopGroup, $idShop);
                        }
                        if ($multishopOverride[self::PASSWORD]) {
                            Configuration::updateValue(self::PASSWORD, $password, false, $idShopGroup, $idShop);
                        }
                        if ($multishopOverride[self::CHECK_REPO]) {
                            Configuration::updateValue(self::CHECK_REPO, $repo, false, $idShopGroup, $idShop);
                        }
                    }
                }
            } else {
                Configuration::updateValue(self::AUTO_UPDATE, $autoUpdate);
                Configuration::updateValue(self::AUTHENTICATE, $auth);
                Configuration::updateValue(self::USERNAME_OR_TOKEN, $username);
                Configuration::updateValue(self::PASSWORD, $password);
                Configuration::updateValue(self::CHECK_REPO, $repo);
            }
        } else {
            $this->addError($this->l('Invalid configuration'));
        }

        return false;
    }

    /**
     * Check for module updates
     */
    protected function checkUpdate()
    {
        // Initialize GitHub Client
        $client = new \Github\Client(
            new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))
        );

        // Authenticate with GitHub
        if (Configuration::get(self::AUTHENTICATE)) {
            $client->authenticate(Configuration::get(self::USERNAME_OR_TOKEN), Configuration::get(self::PASSWORD), \GitHub\Client::AUTH_HTTP_PASSWORD);
        }

        // Get repository from DB
        $repo = Configuration::get(self::CHECK_REPO);
        $repo = explode('/', $repo);

        // Check the release tag
        try {
            $latestRelease = $client->api('repo')->releases()->all($repo[0], $repo[1]);
            if (!empty($latestRelease)) {
                $this->addWarning(sprintf($this->l('New module update available: %s'), $latestRelease[0]['tag_name']));
            }
        } catch (Exception $e) {
            $this->addWarning($e->getMessage());
        }
    }

    /**
     * Add information message
     *
     * @param string $message Message
     */
    protected function addInformation($message)
    {
        if (!Tools::isSubmit('configure')) {
            $this->context->controller->informations[] = '<a href="'.$this->baseUrl.'">'.$this->displayName.': '.$message.'</a>';
        } else {
            $this->context->controller->informations[] = $message;
        }
    }

    /**
     * Add confirmation message
     *
     * @param string $message Message
     */
    protected function addConfirmation($message)
    {
        if (!Tools::isSubmit('configure')) {
            $this->context->controller->confirmations[] = '<a href="'.$this->baseUrl.'">'.$this->displayName.': '.$message.'</a>';
        } else {
            $this->context->controller->confirmations[] = $message;
        }
    }

    /**
     * Add warning message
     *
     * @param string $message Message
     */
    protected function addWarning($message)
    {
        if (!Tools::isSubmit('configure')) {
            $this->context->controller->warnings[] = '<a href="'.$this->baseUrl.'">'.$this->displayName.': '.$message.'</a>';
        } else {
            $this->context->controller->warnings[] = $message;
        }
    }

    /**
     * Add error message
     *
     * @param string $message Message
     */
    protected function addError($message)
    {
        if (!Tools::isSubmit('configure')) {
            // Do not add error in this case
            // It will break execution of AdminController
            $this->context->controller->warnings[] = '<a href="'.$this->baseUrl.'">'.$this->displayName.': '.$message.'</a>';
        } else {
            $this->context->controller->errors[] = $message;
        }
    }

    /**
     * Validate GitHub repository
     *
     * @param string $repo Repository: username/repository
     * @return bool Whether the repository is valid
     */
    protected function validateRepo($repo)
    {
        return count(explode('/', $repo)) === 2;
    }
}
