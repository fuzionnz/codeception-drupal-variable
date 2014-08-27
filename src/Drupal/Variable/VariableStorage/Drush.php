<?php

namespace Codeception\Module\Drupal\Variable\VariableStorage;

use Codeception\Exception\ModuleConfig;

/**
 * Read/Write variables using a bootrapped drupal instance.
 */
class Drush implements StorageInterface
{
    /**
     * Constructor. Receive and store module config.
     *
     * @param array $config
     *   The module config.
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->alias = $this->config['drush_alias'];

        $ret = $out = null;
        exec('drush', $out, $ret);
        if ($ret !== 0) {
            throw new ModuleConfig('Unable to execute drush');
        }
    }

    /**
     * Write a drupal variable.
     *
     * @param string $name
     *   The variable name.
     * @param mixed $value
     *   The variable value (not serialized).
     */
    public function writeVariable($name, $value)
    {
        $this->execDrush(
          sprintf(
            "vset --format=json %s %s",
            escapeshellarg($name),
            escapeshellarg(json_encode($value))
          )
        );
    }

    /**
     * Read a drupal variable.
     *
     * @param string $name
     *   The variable name.
     * @param null $default
     *   Pass in a default to use of the variable doesn't exist.
     *
     * @return bool|mixed
     *   false on failure, the variable on success.
     */
    public function readVariable($name, $default = null)
    {
        $serialized = $this->execDrush("vget --format=php --exact " . escapeshellarg($name) . " 2>/dev/null");

        // Loop through returned lines, until we can unserialize something.
        // This skips any SSH warnings, e.g.
        // Warning: Permanently added 'X.X.X.X' (RSA) to the list of known hosts.
        foreach (explode("\n", $serialized) as $line) {
            if ($line == serialize(false) || ($val = @unserialize($line)) !== false) {
                return $val[$name];
            }
        }

        return $default;
    }

    /**
     * Remove a drupal variable.
     *
     * @param string $name
     *   The variable name.
     */
    public function deleteVariable($name)
    {
        $this->execDrush("vdel -y " . escapeshellarg($name));
    }

    /**
     * Execute drush command.
     *
     * @param $command
     *   Execute drush command. Must escape arguments.
     *
     * @return string
     *   The output of the drush command.
     */
    protected function execDrush($command)
    {
        $cmd = "drush " . escapeshellarg($this->alias) . " $command";
        return trim(shell_exec($cmd));
    }

    /**
     * @return array
     */
    public static function getRequiredFields()
    {
        return array("drush_alias");
    }
}
