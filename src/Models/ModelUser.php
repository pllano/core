<?php 
/**
 * Pllano Core (https://pllano.com)
 *
 * @link https://github.com/pllano/core
 * @version 1.0.1
 * @copyright Copyright (c) 2017-2018 PLLANO
 * @license http://opensource.org/licenses/MIT (MIT License)
 */
namespace Pllano\Core\Models;

use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Pllano\Interfaces\ModelInterface;
use Pllano\Interfaces\ModelsInterfaces\UserInterface;
use Pllano\Core\{Model, Data};

class ModelUser extends Model implements ModelInterface, UserInterface
{

    private $user = [];
    private $modules = [];
    
    public function __construct(Container $app)
    {
		$this->_table = 'user';
        $this->_idField = 'id';
		$this->user = new Data([]);
		parent::__construct($app);
        $this->connectContainer();
        $this->modules = $this->app->get('modules');
    }

    // Запускаем сессию пользоваетеля
    public function run()
    {
        // Читаем ключи
        $session_key = $this->config['key']['session'];
        $cookie_key = $this->config['key']['cookie'];
        $crypt = $this->config['vendor']['crypto']['crypt'];
        
        $session_name = $this->config['settings']['session']['name'];
        $get_cookie = get_cookie($session_name);
        if ($get_cookie != null) {
            try {
                $cookie = $crypt::decrypt($get_cookie, $cookie_key);
            } catch (\Exception $ex) {
                $cookie = null;
            }
            if ($cookie != null) {
                $query = [
                    "cookie" => $cookie, 
                    "state" => 1
                ];

				// Database GET
                $responseArr = $this->db->get($this->_table, $query);

                if (isset($responseArr['0'])) {
                    if(is_object($responseArr['0'])) {
                        $this->user = (array)$responseArr['0'];
                    }

                        if ($this->user['state'] == 1) {
                            $this->session->authorize = 1;
                            $this->session->role_id = $this->user["role_id"];
                            if($this->session->role_id == 100) {
                                if(!isset($this->session->admin_uri)) {
                                    $this->session->admin_uri = random_alias_id();
                                }
                            }
                            $this->session->user_id = $this->user['id'];
                            $this->session->iname = $crypt::encrypt($this->user["iname"], $session_key);
                            $this->session->fname = $crypt::encrypt($this->user["fname"], $session_key);
                            $this->session->phone = $crypt::encrypt($this->user["phone"], $session_key);
                            $this->session->email = $crypt::encrypt($this->user["email"], $session_key);
                        } else {
                            $this->session->authorize = null;
                            $this->session->role_id = null;
                            $this->session->user_id = null;
                            unset($this->session->authorize); // удаляем authorize
                            unset($this->session->role_id); // удаляем role_id
                            unset($this->session->user_id); // удаляем role_id
                            $this->session->destroy();
                            $this->session->clear();
                        }
                } else {
                    $this->session->authorize = null;
                    $this->session->role_id = null;
                    $this->session->user_id = null;
                    unset($this->session->authorize); // удаляем authorize
                    unset($this->session->role_id); // удаляем role_id
                    unset($this->session->user_id); // удаляем role_id
                }
            }
        } else {
            // Если cookie нет создаем новую
            if ($get_cookie === null) {
                // Чистим сессию на всякий случай
                $this->session->clear();
                // Генерируем identificator
                $get_cookie = $crypt::encrypt(random_token(), $cookie_key);
                // Записываем пользователю новый cookie
                set_cookie($session_name, $get_cookie, 60*60*24*365);
                // Пишем в сессию get_cookie cookie
                $this->session->cookie = $get_cookie;
            }
        }
        
        if (!isset($this->session->language)) {
            $langs = new $this->config['vendor']['detector']['language']();
            if ($langs->getLanguage()) {
                $this->session->language = $langs->getLanguage();
            }
        }
    }

    // Авторизвация
    public function checkLogin($email, $phone, $password)
    {
        $query = [
            "phone" => $phone, 
            "email" => $email
        ];

		// Database GET
        $responseArr = $this->db->get($this->_table, $query) ?? [];

        if (isset($responseArr)) {
            $this->user = (array)$responseArr["0"];
            // Если все ок читаем пароль
            if (password_verify($password, $this->user["password"])) {
                // Если все ок - отдаем user_id
                return $this->user["user_id"];
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    // Обновляем cookie в базе
    public function putUserCode($user_id)
    {
        // Подключаем сессию
        $session = $this->session;
        $session_name = $this->config['settings']['session']['name'];
        // Генерируем новый cookie
        $cookie = random_token();

        $query = [
            "cookie" => $cookie, 
            "authorized" => today()
        ];

		// Database PUT
        $responseArr = $this->db->put($this->_table, $query, $user_id) ?? [];

        // Если удалось обновить cookie в базе перезапишем везде
        if (isset($responseArr)) {
            // Читаем ключи шифрования
            $cookie_key = $this->config['key']['cookie'];
            $crypt = $this->config['vendor']['crypto']['crypt'];
            // Шифруем cookie
            $new_cookie = $crypt::encrypt($cookie, $cookie_key);
            // Перезаписываем cookie в сессии
            $this->session->cookie = $new_cookie;
            // Перезаписываем cookie в базе
            set_cookie($session_name, $new_cookie, 60*60*24*365);
            // Если все ок возвращаем 1
            return 1;
        } else {
            // Если не удалось перезаписать в базе
            return null;
        }
    }

    // Проверяем наличие пользователя по email и phone
    public function getEmailPhone($email, $phone)
    {
        $r = null;
		$query["email"] = $email;
        $query["phone"] = $phone;

		// Database GET
        $responseArr = $this->db->get($this->_table, $query) ?? [];

        if (isset($responseArr)) {
            $item = (array)$responseArr["0"];
            if(isset($item["user_id"])){
                    $r = $item["user_id"];
            }
        }
		return $r;
    }

    // Выйти
    static public function logOuts(Request $request)
    {
        $modulVal = $this->modules['logout']['logout'];
        $modules = new $modulVal['vendor']($this->app, $this->route, 'logout', 'logout', $modulVal);
        $modules->post($request);
    }

    // Регистрируем-авторизуем посетителя и обновляем его контакты
    public function registerMe()
    {
        
    }

    // Регистрируем-авторизуем посетителя и обновляем его контакты
    public function authorizeMe()
    {
        
    }

    // Ищем пользователя и среди неактивных тоже
    private function checkDoubleUser()
    {
        
    }

    // Ищем имейлы
    private function checkDoubleEmail()
    {
        
    }

    // Ищем телефон
    private function checkDoublePhone()
    {
        
    }

    public function checkAuth()
    {
        
    }

    // Удаляем регистрацию, корзину, все товары в ней - отладочная функция
    static public function unregisterMe()
    {
        
    }

    // Что ?
    static public function removeRegistration()
    {
        
    }

    // Генерируем проверочный код для регистрации, действительный 15 минут
    static public function codeGenerate()
    {
        
    }

    // Проверка проверочного кода. Если есть и не просрочен
    static public function codeCheck()
    {
        
    }

    // Высылаем проверочный код на почту
    public function sendCode($email, $code, $phone, $id)
    {
        
    }

}
 