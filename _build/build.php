<?php

class MODX280patchPackage
{
    /** @var modX $modx */
    public $modx;
    /** @var array $config */
    public $config = [];

    /** @var modPackageBuilder $builder */
    public $builder;
    /** @var modCategory $vehicle */
    public $category;
    public $category_attributes = [];

    protected $_idx = 1;


    /**
     * MODX-2.8.0-patchPackage constructor.
     *
     * @param $core_path
     * @param array $config
     */
    public function __construct($core_path, array $config = [])
    {
        /** @noinspection PhpIncludeInspection */
        require($core_path . 'model/modx/modx.class.php');
        /** @var modX $modx */
        $this->modx = new modX();
        $this->modx->initialize('mgr');
        $this->modx->getService('error', 'error.modError');

        $root = dirname(dirname(__FILE__)) . '/';
        $assets = $root . 'assets/components/' . $config['name_lower'] . '/';
        $manager_path = $root . 'manager/';
        $core = $root . 'core/';

        $this->config = array_merge([
            'log_level' => modX::LOG_LEVEL_INFO,
            'log_target' => XPDO_CLI_MODE ? 'ECHO' : 'HTML',

            'root' => $root,
            'build' => $root . '_build/',
            'elements' => $root . '_build/elements/',
            'resolvers' => $root . '_build/resolvers/',

            'assets' => $assets,
            'manager' => $manager_path,
            'core' => $core,
        ], $config);
        $this->modx->setLogLevel($this->config['log_level']);
        $this->modx->setLogTarget($this->config['log_target']);

        $this->initialize();
    }


    /**
     * Initialize package builder
     */
    protected function initialize()
    {
        $this->builder = $this->modx->getService('transport.modPackageBuilder');
        $this->builder->createPackage($this->config['name_lower'], $this->config['version'], $this->config['release']);
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Created Transport Package.');
        
        $this->category = $this->modx->newObject('modCategory');
        $this->category->set('category', $this->config['name']);
        $this->category_attributes = [
            xPDOTransport::UNIQUE_KEY => 'category',
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::RELATED_OBJECTS => true,
            xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [],
        ];
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Created main Category.');
    }


    /**
     *  Install package
     */
    protected function install()
    {
        $signature = $this->builder->getSignature();
        $sig = explode('-', $signature);
        $versionSignature = explode('.', $sig[1]);

        /** @var modTransportPackage $package */
        if (!$package = $this->modx->getObject('transport.modTransportPackage', ['signature' => $signature])) {
            $package = $this->modx->newObject('transport.modTransportPackage');
            $package->set('signature', $signature);
            $package->fromArray([
                'created' => date('Y-m-d h:i:s'),
                'updated' => null,
                'state' => 1,
                'workspace' => 1,
                'provider' => 0,
                'source' => $signature . '.transport.zip',
                'package_name' => $this->config['name'],
                'version_major' => $versionSignature[0],
                'version_minor' => !empty($versionSignature[1]) ? $versionSignature[1] : 0,
                'version_patch' => !empty($versionSignature[2]) ? $versionSignature[2] : 0,
            ]);
            if (!empty($sig[2])) {
                $r = preg_split('#([0-9]+)#', $sig[2], -1, PREG_SPLIT_DELIM_CAPTURE);
                if (is_array($r) && !empty($r)) {
                    $package->set('release', $r[0]);
                    $package->set('release_index', (isset($r[1]) ? $r[1] : '0'));
                } else {
                    $package->set('release', $sig[2]);
                }
            }
            $package->save();
        }
        if ($package->install()) {
            $this->modx->runProcessor('system/clearcache');
        }
    }


    /**
     * @return modPackageBuilder
     */
    public function process()
    {
        // Create main vehicle
        /** @var modTransportVehicle $vehicle */
        $vehicle = $this->builder->createVehicle($this->category, $this->category_attributes);
        
        // Files resolvers
        $vehicle->resolve('file', [
            'source' => $this->config['core'],
            'target' => "return dirname(MODX_CORE_PATH);",
        ]);
        $vehicle->resolve('file', [
            'source' => $this->config['manager'],
            'target' => "return dirname(MODX_MANAGER_PATH);",
        ]);

        // Add resolvers into vehicle
        $resolvers = scandir($this->config['resolvers']);
        foreach ($resolvers as $resolver) {
            if (in_array($resolver[0], ['_', '.'])) {
                continue;
            }
            if ($vehicle->resolve('php', ['source' => $this->config['resolvers'] . $resolver])) {
                $this->modx->log(modX::LOG_LEVEL_INFO, 'Added resolver ' . preg_replace('#\.php$#', '', $resolver));
            }
        }
        $this->builder->putVehicle($vehicle);

        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packing up transport package zip...');
        $this->builder->pack();

        if (!empty($this->config['install'])) {
            $this->install();
        }

        return $this->builder;
    }

}

/** @var array $config */
if (!file_exists(dirname(__FILE__) . '/config.inc.php')) {
    exit('Could not load MODX config. Please specify correct MODX_CORE_PATH constant in config file!');
}
$config = require(dirname(__FILE__) . '/config.inc.php');
$install = new MODX280patchPackage(MODX_CORE_PATH, $config);
$builder = $install->process();

if (!empty($config['download'])) {
    $name = $builder->getSignature() . '.transport.zip';
    if ($content = file_get_contents(MODX_CORE_PATH . '/packages/' . $name)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $name);
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($content));
        exit($content);
    }
}
