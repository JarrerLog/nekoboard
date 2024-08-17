<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use App\Models\User;
use App\Utils\Helper;
use Illuminate\Support\Facades\DB;

class NekoInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'neko:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'NekoInstall';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $this->info("

                    _   _      _         _                         _ 
                    | \ | | ___| | _____ | |__   ___   __ _ _ __ __| |
                    |  \| |/ _ \ |/ / _ \| '_ \ / _ \ / _` | '__/ _` |
                    | |\  |  __/   < (_) | |_) | (_) | (_| | | | (_| |
                    |_| \_|\___|_|\_\___/|_.__/ \___/ \__,_|_|  \__,_|

                    ");
            if (\File::exists(base_path() . '/.env')) {
                $securePath = config('v2board.secure_path', config('v2board.frontend_admin_path', "neko"));
                $this->info("https://<ip/domain>/{$securePath}");
                abort(500, 'File .env đã tồn tại');
            }

            if (!copy(base_path() . '/.env.example', base_path() . '/.env')) {
                abort(500, 'Gói cài đặt đã bị lỗi vui lòng tải lại');
            }
            $this->saveToEnv([
                'APP_KEY' => 'base64:' . base64_encode(Encrypter::generateKey('AES-256-CBC')),
                'DB_HOST' => $this->ask('Địa chỉ database (mặc định: localhost)', 'localhost'),
                'DB_DATABASE' => $this->ask('Tên database '),
                'DB_USERNAME' => $this->ask('Tên đăng nhập database'),
                'DB_PASSWORD' => $this->ask('Mật khẩu database')
            ]);
            \Artisan::call('config:clear');
            \Artisan::call('config:cache');
            try {
                DB::connection()->getPdo();
            } catch (\Exception $e) {
                abort(500, 'Lỗi kết nối tới database vui lòng kiểm tra database');
            }
            $file = \File::get(base_path() . '/database/install.sql');
            if (!$file) {
                abort(500, 'File cài đặt bị lỗi vui lòng tải lại');
            }
            $sql = str_replace("\n", "", $file);
            $sql = preg_split("/;/", $sql);
            if (!is_array($sql)) {
                abort(500, 'File cài đặt bị lỗi vui lòng tải lại');
            }
            $this->info('Đang nhập database...');
            foreach ($sql as $item) {
                try {
                    DB::select(DB::raw($item));
                } catch (\Exception $e) {
                }
            }
            $this->info('Tạo thông tin admin:');
            $email = '';
            $password = '';
            while (!$email) {
                $email = $this->ask('Email admin?');
            }

            while (!$password) {
                $password = $this->ask("Mật khẩu admin?");
            }

            // $password = Helper::guid(false);
            if (!$this->registerAdmin($email, $password)) {
                abort(500, 'Tạo user không thành công');
            }

            $this->info('Thông tin:');
            $this->info("Email admin: {$email}");
            $this->info("Mật khẩu admin: {$password}");

            $defaultSecurePath = "neko";
            $this->info("truy cập http(s)://<ip/domain>/{$defaultSecurePath}");
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    private function registerAdmin($email, $password)
    {
        $user = new User();
        $user->email = $email;
        // if (strlen($password) < 8) {
        //     abort(500, '管理员密码长度最小为8位字符');
        // }
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->uuid = Helper::guid(true);
        $user->token = Helper::guid();
        $user->is_admin = 1;
        return $user->save();
    }

    private function saveToEnv($data = [])
    {
        function set_env_var($key, $value)
        {
            if (!is_bool(strpos($value, ' '))) {
                $value = '"' . $value . '"';
            }
            $key = strtoupper($key);

            $envPath = app()->environmentFilePath();
            $contents = file_get_contents($envPath);

            preg_match("/^{$key}=[^\r\n]*/m", $contents, $matches);

            $oldValue = count($matches) ? $matches[0] : '';

            if ($oldValue) {
                $contents = str_replace("{$oldValue}", "{$key}={$value}", $contents);
            } else {
                $contents = $contents . "\n{$key}={$value}\n";
            }

            $file = fopen($envPath, 'w');
            fwrite($file, $contents);
            return fclose($file);
        }
        foreach ($data as $key => $value) {
            set_env_var($key, $value);
        }
        return true;
    }
}
