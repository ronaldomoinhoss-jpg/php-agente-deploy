<?php
require_once __DIR__ . '/../models/Material.php';

class MaterialController {
    private $model;

    public function __construct() {
        $this->model = new Material();
    }

    public function listar($busca = '', $tipo = '') {
        return $this->model->listarTodos($busca, $tipo);
    }

    public function buscar($id) {
        return $this->model->buscarPorId($id);
    }

    public function salvar($data) {
        return $this->model->salvar($data);
    }

    public function excluir($id) {
        return $this->model->excluir($id);
    }
}
