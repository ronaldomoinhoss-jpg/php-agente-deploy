<?php
require_once __DIR__ . '/../models/Regra.php';

class RegraController {
    private $model;

    public function __construct() {
        $this->model = new Regra();
    }

    public function listar() {
        return $this->model->listarTodas();
    }

    public function salvar($data) {
        return $this->model->salvar($data);
    }

    public function excluir($id) {
        return $this->model->excluir($id);
    }
}
