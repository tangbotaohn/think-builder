<?php
namespace thinkbuilder;

use thinkbuilder\generator\{
    IGenerator, Generator, HtmlGenerator, JSGenerator, MenuGenerator, ProfileGenerator, PHPGenerator, SQLGenerator
};

use thinkbuilder\helper\{
    TemplateHelper, FileHelper, ClassHelper
};
use thinkbuilder\node\Node;

/**
 * Class Builder 构建程序
 */
class Builder
{
    //配置参数
    private $config = [];
    //系统基本路径
    private $paths = [
        'target' => './deploy',
        'application' => './deploy',
        'database' => './deploy/' . DBFILE_PATH,
        'profile' => './deploy/' . PROFILE_PATH,
        'public' => './deploy/' . PUB_PATH
    ];

    //数据
    private $data = [];
    //默认的 git 仓库
    public $repository = '';

    //版本
    protected $version = '1.3.0';

    public function __construct($params = [])
    {
        if (key_exists('config', $params)) $this->setConfigFromFile($params['config']);
        if (key_exists('data', $params)) $this->setDataFromFile($params['data']);
        if (key_exists('target', $params)) $this->paths['target'] = $params['target'];
        if (key_exists('repository', $params)) $this->repository = $params['repository'];
    }

    /**
     * 通过数组设置项目配置信息
     * @param array $config
     */
    public function setConfig($config = [])
    {
        $this->config = $config;
    }

    /**
     * 通过指定的文件名获取并设置项目配置信息
     * @param $file
     */
    public function setConfigFromFile($file)
    {
        $this->config = require $file;
    }

    /**
     * 设置数据
     * @param array $data
     */
    public function setData($data = [])
    {
        $this->data = $data;
    }

    /**
     * 通过文件读取并设置数据
     * @param $file
     */
    public function setDataFromFile($file)
    {
        $this->data = require $file;
    }

    protected function gitClone()
    {
        $cmd = 'git clone ' . $this->repository . ' ' . $this->paths['target'] . ' && ' . 'rm -rf ' . $this->paths['target'] . '/.git';
        shell_exec($cmd);
    }

    protected function makeBaseDirectories()
    {
        $this->paths = array_merge($this->paths, [
            'application' => $this->paths['target'],
            'database' => $this->paths['target'] . '/' . DBFILE_PATH,
            'profile' => $this->paths['target'] . '/' . PROFILE_PATH,
            'public' => $this->paths['target'] . '/' . PUB_PATH
        ]);

        FileHelper::mkdirs($this->paths);
    }

    protected function decompressAssets()
    {
        $_assets_file = ASSETS_PATH . '/themes/' . $this->config['theme'] . '/assets.tar.bz2';
        $cmd = 'tar xvjf ' . $_assets_file . ' -C' . $this->paths['public'];
        shell_exec($cmd);
    }

    /**
     * 创建项目文件的主方法
     */
    public function build()
    {
        $build_actions = $this->config['actions'];

        //使用 git clone 创建初始目录结构
        $this->gitClone();

        //创建基本目录
        $this->makeBaseDirectories();


        //解压资源文件
        if ($build_actions['decompress_assets']) {
            $this->decompressAssets();
        }

        //装载默认设置并进行缓存
        $cache = Cache::getInstance();
        $cache->set('defaults', $this->config['defaults']);
        $cache->set('config', $this->config);
        $cache->set('paths', $this->paths);

        $project = Node::create('Project', ['data' => $this->data]);
        $project->process();

        //执行composer update命令
        if ($build_actions['run_composer']) {
            $cmd = 'cd ' . $this->paths['target'];
            shell_exec($cmd);
            echo 'updating composer repositories ...' . PHP_EOL;
            $cmd = 'composer update';
            shell_exec($cmd);
        }

        //执行bower install命令
        if ($build_actions['run_bower']) {
            $cmd = 'cd ' . $this->paths['target'];
            shell_exec($cmd);
            echo 'installing bower repositories ...' . PHP_EOL;
            $deps = Cache::getInstance()->get('config')['bower_deps'];
            $cmd = 'bower install ';
            foreach ($deps as $dep) {
                $cmd .= $dep . ' ';
            }
            $cmd .= '--save';
            if (count($deps) != 0) shell_exec($cmd);
        }

        echo "ThinkForge Builder, Version: " . $this->version . PHP_EOL;
    }
}
