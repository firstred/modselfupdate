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
    const LAST_UPDATE = 'MODSELFUPDATE_LAST_UPDATE';
    const AUTHENTICATE = 'MODSELFUPDATE_AUTHENTICATE';
    const USERNAME_OR_TOKEN = 'MODSELFUPDATE_USERNAME';
    const PASSWORD = 'MODSELFUPDATE_PASSWORD';
    const CHECK_REPO = 'MODSELFUPDATE_CHECK_REPO';
    const LATEST_VERSION = 'MODSELFUPDATE_LATEST_VERSION';
    const DOWNLOAD_URL = 'MODSELFUPDATE_DOWNLOAD_URL';

    const CHECK_INTERVAL = 86400;
    const UPDATE_INTERVAL = 60;

    /** @var string $baseUrl Module base URL */
    public $baseUrl;

    public $latestVersion;
    public $lastCheck;
    public $downloadUrl;
    public $needsUpdate;

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
        $this->need_instance = 1;

        parent::__construct();
        $this->displayName = $this->l('Self updating module');
        $this->description = $this->l('Uses GitHub in order to update automatically');

        // Only check from Back Office
        if ($this->context->cookie->id_employee) {
            $this->baseUrl = $this->context->link->getAdminLink('AdminModules', true).'&'.http_build_query(array(
                    'configure' => $this->name,
                    'tab_module' => $this->tab,
                    'module_name' => $this->name,
                ));

            $this->lastCheck = Configuration::get(self::LAST_CHECK);
            $this->checkUpdate();
        }
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
        Configuration::updateGlobalValue(self::LATEST_VERSION, '0.0.0');

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
        Configuration::deleteByName(self::AUTO_UPDATE);
        Configuration::deleteByName(self::LAST_CHECK);
        Configuration::deleteByName(self::AUTHENTICATE);
        Configuration::deleteByName(self::USERNAME_OR_TOKEN);
        Configuration::deleteByName(self::PASSWORD);
        Configuration::deleteByName(self::CHECK_REPO);
        Configuration::deleteByName(self::LATEST_VERSION);
        Configuration::deleteByName(self::DOWNLOAD_URL);

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

        $this->context->smarty->assign(array(
            'curentVersion' => $this->version,
            'latestVersion' => $this->latestVersion,
            'lastCheck' => $this->lastCheck,
            'needsUpdate' => $this->needsUpdate,
            'baseUrl' => $this->baseUrl,
        ));

        $output .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/versioncheck.tpl');

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
        $lastCheck = (int) Configuration::get(self::LAST_CHECK);
        $lastUpdate = (int) Configuration::get(self::LAST_UPDATE);

        if ($lastCheck < (time() - self::CHECK_INTERVAL) || Tools::getValue($this->name.'CheckUpdate')) {
            $this->lastCheck = time();
            Configuration::updateGlobalValue(self::LAST_CHECK, time());

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
                $latestRelease = $client->api('repo')->releases()->latest($repo[0], $repo[1]);
                if (isset($latestRelease['tag_name']) && $latestRelease['tag_name']) {
                    if (version_compare($this->version, $latestRelease['tag_name'], '<') &&
                        isset($latestRelease['assets'][0]['browser_download_url'])) {
                        Configuration::updateGlobalValue(self::LATEST_VERSION, $latestRelease['tag_name']);
                        Configuration::updateGlobalValue(self::DOWNLOAD_URL, $latestRelease['assets'][0]['browser_download_url']);
                        $this->latestVersion = $latestRelease['tag_name'];
                        $this->downloadUrl = $latestRelease['assets'][0]['browser_download_url'];
                    }
                }
            } catch (Exception $e) {
                $this->addWarning($e->getMessage());
            }
        } else {
            $this->latestVersion = Configuration::get(self::LATEST_VERSION);
            $this->downloadUrl = Configuration::get(self::DOWNLOAD_URL);
        }

        $this->needsUpdate = version_compare($this->version, $this->latestVersion, '<');

        if ($this->needsUpdate &&
            (Configuration::get(self::AUTO_UPDATE) && $lastUpdate < (time() - self::UPDATE_INTERVAL) || Tools::getValue($this->name.'ApplyUpdate'))
        ) {
            $zipLocation = _PS_MODULE_DIR_.$this->name.'.zip';
            if (@!file_exists($zipLocation)) {
                file_put_contents($zipLocation, fopen($this->downloadUrl, 'r'));
            }
            if (@file_exists($zipLocation)) {
                $this->extractArchive($zipLocation);
            } else {
                // We have an outdated URL, reset last check
                Configuration::updateGlobalValue(self::LAST_CHECK, 0);
            }
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
            $this->context->controller->errors[] = '<a href="'.$this->baseUrl.'">'.$this->displayName.': '.$message.'</a>';
        } else {
            // Do not add error in this case
            // It will break execution of AdminController
            $this->context->controller->warnings[] = $message;
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

    /**
     * Extract module archive
     *
     * @param string $file     File location
     * @param bool   $redirect Whether there should be a redirection after extracting
     * @return bool
     */
    protected function extractArchive($file, $redirect = true)
    {
        $zipFolders = array();
        $tmpFolder = _PS_MODULE_DIR_.'selfupdate'.md5(time());

        if (@!file_exists($file)) {
            $this->addError($this->l('Module archive could not be downloaded'));

            return false;
        }

        $success = false;
        if (substr($file, -4) == '.zip') {
            if (Tools::ZipExtract($file, $tmpFolder) && file_exists($tmpFolder.DIRECTORY_SEPARATOR.$this->name)) {
                if (@rename(_PS_MODULE_DIR_.$this->name, _PS_MODULE_DIR_.$this->name.'backup') && @rename($tmpFolder.DIRECTORY_SEPARATOR.$this->name, _PS_MODULE_DIR_.$this->name)) {
                    $this->recursiveDeleteOnDisk(_PS_MODULE_DIR_.$this->name.'backup');
                    $success = true;
                } else {
                    if (file_exists(_PS_MODULE_DIR_.$this->name.'backup')) {
                        $this->recursiveDeleteOnDisk(_PS_MODULE_DIR_.$this->name);
                        @rename(_PS_MODULE_DIR_.$this->name.'backup', _PS_MODULE_DIR_.$this->name);
                    }
                }
            }
        } else {
            require_once(_PS_TOOL_DIR_.'tar/Archive_Tar.php');
            $archive = new Archive_Tar($file);
            if ($archive->extract($tmpFolder)) {
                $zipFolders = scandir($tmpFolder);
                if ($archive->extract(_PS_MODULE_DIR_)) {
                    $success = true;
                }
            }
        }

        if (!$success) {
            $this->addError($this->l('There was an error while extracting the update (file may be corrupted).'));
            // Force a new check
            Configuration::updateGlobalValue(self::LAST_CHECK, 0);
        } else {
            //check if it's a real module
            foreach ($zipFolders as $folder) {
                if (!in_array($folder, array('.', '..', '.svn', '.git', '__MACOSX')) && !Module::getInstanceByName($folder)) {
                    $this->addError(sprintf($this->l('The module %1$s that you uploaded is not a valid module.'), $folder));
                    $this->recursiveDeleteOnDisk(_PS_MODULE_DIR_.$folder);
                }
            }
        }

        @unlink($file);
        $this->recursiveDeleteOnDisk($tmpFolder);


        if ($success) {
            Configuration::updateGlobalValue(self::LAST_UPDATE, (int) time());
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            if ($redirect) {
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true).'&doNotAutoUpdate=1');
            }
        }

        return $success;
    }

    /**
     * Delete folder recursively
     *
     * @param string $dir Directory
     */
    protected function recursiveDeleteOnDisk($dir)
    {
        if (strpos(realpath($dir), realpath(_PS_MODULE_DIR_)) === false) {
            return;
        }

        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != '.' && $object != '..') {
                    if (filetype($dir.'/'.$object) == 'dir') {
                        $this->recursiveDeleteOnDisk($dir.'/'.$object);
                    } else {
                        @unlink($dir.'/'.$object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }
}
