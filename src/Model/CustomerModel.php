<?php

declare(strict_types=1);

namespace App\Model;

final class CustomerModel
{
    protected $database;

    protected function db()
    {
        $pdo = new \Pecee\Pixie\QueryBuilder\QueryBuilderHandler($this->database);
        return $pdo;
    }

    public function __construct(\Pecee\Pixie\Connection $database)
    {
        $this->database       = $database;
    }

    public function get($keywords = "")
    {
        $getData = $this->db()->table('customer');
        $getData->select($getData->raw("customer.id, customer.name"));

        if(!empty($keywords)) {
            $getData->where(function ($relation) use ($keywords) {
                $relation->where($relation->raw('lower(customer.name)'), 'LIKE', $relation->raw("LOWER('%" . $keywords . "%')"));
            });
        }

        return $getData->get();
    }

    public function add($name)
    {
        $getData = $this->db()->table('customer');
        $getData->select($getData->raw('customer.id'));
        $getData->where('customer.name', '=', $name);
        $checkData = $getData->count();

        $status                     = false;
        if ($checkData == 0) {
            $insertdata['name']         = $name;
            $insertdata['created']      = date('Y-m-d H:i:s');

            $status                     = $this->db()->table('customer')->insert($insertdata);
        }
        return $status;
    }

    public function update($id, $name)
    {
        $getData = $this->db()->table('customer');
        $getData->select($getData->raw('customer.id'));
        $getData->where('customer.name', '=', $name);
        $getData->whereNot('customer.id', '=', $id);
        $checkData = $getData->count();

        if ($checkData == 0) {
            $data['name']         = $name;

            $this->db()->table('customer')->where('customer.id', '=', $id)->update($data);
        }
        return true;
    }

    public function delete($id)
    {
        $status = true;
        $this->db()->table('customer')->where('customer.id', '=', $id)->delete();
        return $status;
    }
}