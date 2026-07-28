<?php
require_once __DIR__ . '/../models/Veiculo.php';

class VeiculoController {
    private $model;

    public function __construct() {
        $this->model = new Veiculo();
    }

    public function listar() {
        return $this->model->listarTodos();
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
