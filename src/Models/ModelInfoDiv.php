<?php /**
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
use Pllano\Core\{Model, Data};

class ModelInfoDiv extends Model implements ModelInterface
{

    private $info_div = [];

    public function __construct(Container $app)
    {
        parent::__construct($app);
        $this->info_div = new Data([]);
        $this->connectContainer();
        $this->connectDatabases();
        $this->_table = 'info_div';
        $this->_idField = 'id';
        $this->_adapter = 'Pdo';
        $this->db->setAdapter($this->_adapter);
        $this->connectDatabases();
    }

}
 