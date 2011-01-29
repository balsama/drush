<?php

/*
 * @file
 *   Programmatically upgrade a site from Drupal 6 to Druapl 7.
 *
 *   We also implicitly test:
 *     - pm-download
 *     - site-install for D6
 *     - user-create
 *     - sql-sync
 *     - updatedb and batch.inc
 */

class siteUpgradeCase extends Drush_TestCase {
  function testUpgrade() {
    $env = 'testUpgrade';
    $this->setUpDrupal($env, TRUE, '6.x');
    $root = $this->sites[$env]['root'];
    
    // Create the alias for D7 site.
    $aliases['testUpgrade'] = array(
      'root' => UNISH_SANDBOX . '/target',
      'uri' => 'testUpgrade',
      'db-url' => UNISH_DB_URL . '/unish_target',
    );
    $contents = $this->file_aliases($aliases);
    $alias_path = "$root/aliases.drushrc.php";
    file_put_contents($alias_path, $contents);
    
    // Create a user in D6.
    $name = "example";
    $options = array(
      'mail' => "example@example.com",
      'password' => 'password',
      'root' => $root,
      'uri' => $env,
    );
    $this->drush('user-create', array($name), $options);
    
    // Perform the upgrade.
    $options = array(
      'yes' => NULL,
      'root' => $root,
      'uri' => $env,
    );
    $this->drush('site-upgrade', array('@testUpgrade'), $options);
    
    // Assert that the D7 site bootstraps.
    $options = array(
      'pipe' => NULL,
      'root' => $aliases['testUpgrade']['root'],
      'uri' => $aliases['testUpgrade']['uri'],
    );
    $return = $this->drush('core-status', array('drupal_bootstrap'), $options);
    $this->assertEquals('Successful', $this->getOutput(), 'The target site bootstraps successfully');
    
    // Assures that a updatedb and batch updates work properly. See user_update_7001().
    $options = array(
      'root' => $aliases['testUpgrade']['root'],
      'uri' => $aliases['testUpgrade']['uri'],
    );
    $eval = "require_once DRUPAL_ROOT . '/' . variable_get('password_inc', 'includes/password.inc');";
    $eval .= "\$account = user_load_by_name('example');";
    $eval .= "print (string) user_check_password('password', \$account)";
    $this->drush('php-eval', array($eval), $options);
    $output = $this->getOutput();
    $this->assertEquals(1, $output, 'User was updated to new password format.');
  }
}