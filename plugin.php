<?php defined( 'App' ) or die( 'BoidCMS' );
/**
 *
 * CRON – Web-Based Task Scheduler
 *
 * @package Plugin_Cron
 * @author Shuaib Yusuf Shuaib
 * @version 0.1.0
 */

if ( 'cron' !== basename( __DIR__ ) ) return;

global $App;
$App->set_action( 'install', 'cron_install' );
$App->set_action( 'uninstall', 'cron_uninstall' );
$App->set_action( 'rendered', 'cron_exec', 20 );
$App->set_action( 'admin', 'cron_admin' );

/**
 * Initialize CRON, first time install
 * @param string $plugin
 * @return void
 */
function cron_install( string $plugin ): void {
  global $App;
  if ( 'cron' === $plugin ) {
    $file    = $App->root( 'data/cron.json' );
    file_put_contents( $file, '{}', LOCK_EX );
  }
}

/**
 * Free hosting space, while uninstalled
 * @param string $plugin
 * @return void
 */
function cron_uninstall( string $plugin ): void {
  global $App;
  if ( 'cron' === $plugin ) {
    $file =  $App->root( 'data/cron.json' );
    if ( is_file( $file ) ) unlink( $file );
  }
}

/**
 * Runs due cron tasks if inactive
 * @return void
 */
function cron_exec(): void {
  if ( ! is_cron_running() ) {
    touch( cron_file() );
    run_cron_tasks();
  }
}

/**
 * Admin settings
 * @return void
 */
function cron_admin(): void {
  global $App, $layout, $page;
  switch ( $page ) {
    case 'cron':
      $layout[ 'title' ] = 'CRON';
      $layout[ 'content' ] = '
      <ul class="ss-list ss-fieldset ss-mobile ss-w-6 ss-mx-auto">
        <li class="ss-bd-none">
          <h3 class="ss-monospace">Scheduled Tasks</h3>
          <hr class="ss-hr">
        </li>';
      $cron_tasks = get_cron_tasks();
      foreach ( $cron_tasks as $id => $cron ) {
        $layout[ 'content' ] .= '
        <li class="ss-responsive">
          <h4 class="ss-monospace">' . $App->esc( $id ) . '</h4>
          <hr class="ss-hr ss-w-3 ss-mx-auto">
          <p><b>One-time Only</b>: ' . ( ! $cron[ 'interval' ] ? 'Yes' : 'No' ) . '</p>
          <p><b>Interval</b>: Every ' . $cron[ 'interval' ] . ' second(s) / ' . floor( $cron[ 'interval' ] / 60 ) . ' minute(s) / ' . floor( ( $cron[ 'interval' ] / 60 ) / 60 ) . ' hour(s)</p>
          <p><b>Dues</b>: ' . $cron[ 'dues' ] . '</p>
        </li>';
      }
      $layout[ 'content' ] .= '</ul>';
      require_once $App->root( 'app/layout.php' );
      break;
  }
}

/**
 * Schedule a cron task
 * @param string $id
 * @param string $callback
 * @param int $interval
 * @param array $args
 * @return bool
 */
function schedule_task( string $id, string $callback, int $interval = 86400, array $args = array() ): bool {
  $cron_tasks = get_cron_tasks();
  if ( isset( $cron_tasks[ $id ] ) ) {
    return false;
  }
  
  $cron_tasks[ $id ] = array();
  $cron_tasks[ $id ][ 'dues' ] = 0;
  $cron_tasks[ $id ][ 'args' ] = $args;
  $cron_tasks[ $id ][ 'interval' ] = $interval;
  $cron_tasks[ $id ][ 'callback' ] = $callback;
  return save_cron_tasks( $cron_tasks );
}

/**
 * Remove a scheduled task
 * @param string $id
 * @return bool
 */
function unschedule_task( string $id ): bool {
  $cron_tasks = get_cron_tasks();
  if ( ! isset( $cron_tasks[ $id ] ) ) {
    return false;
  }
  
  unset( $cron_tasks[ $id ] );
  return save_cron_tasks( $cron_tasks );
}

/**
 * Check if task is scheduled
 * @param string $id
 * @return bool
 */
function is_task_scheduled( string $id ): bool {
  return isset(  get_cron_tasks()[ $id ] );
}

/**
 * Checks if the cron task was executed within the last 5 minutes
 * @return bool
 */
function is_cron_running(): bool {
  $mtime =     filemtime( cron_file() );
  return ( ( time() - $mtime ) <= 300 );
}

/**
 * Storage file
 * @return string
 */
function cron_file(): string {
  global $App;
  return $App->root( 'data/cron.json' );
}

/**
 * Load the cron data
 * @return array
 */
function get_cron_tasks(): array {
  $cron_tasks = file_get_contents( cron_file() );
  return        json_decode( $cron_tasks, true );
}

/**
 * Save the cron data
 * @param array $cron_tasks
 * @return bool
 */
function save_cron_tasks( array $cron_tasks ): bool {
  $cron_tasks =            json_encode( $cron_tasks, JSON_FORCE_OBJECT );
  return ( bool ) file_put_contents( cron_file(), $cron_tasks, LOCK_EX );
}

/**
 * Run due cron tasks
 * @return bool
 */
function run_cron_tasks(): bool {
  $now = time();
  $cron_tasks = get_cron_tasks();
  foreach ( $cron_tasks as $id => $cron ) {
    $interval = $cron[ 'interval' ];
    $last_run = ( $cron[ 'last_run' ] ?? 0 );
    if ( $now >= ( $last_run + $interval ) ) {
      $args = $cron[ 'args' ];
      $callback = $cron[ 'callback' ];
      if ( is_callable( $callback ) ) {
        try {
          call_user_func_array( $callback, $args );
        } catch ( Throwable $e ) {}
      }
      
      $cron_tasks[ $id ][ 'last_run' ] = $now;
      $cron_tasks[ $id ][ 'dues' ]++;
      
      if ( ! $cron[ 'interval' ] ) {
        unset( $cron_tasks[ $id ] );
      }
    }
  }
  
  return save_cron_tasks( $cron_tasks );
}
?>
