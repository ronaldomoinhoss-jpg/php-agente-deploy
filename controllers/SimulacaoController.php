<?php
require_once __DIR__ . '/../models/Simulacao.php';

class SimulacaoController {
    private $model;

    public function __construct() {
        $this->model = new Simulacao();
    }

    public function listar() {
        return $this->model->listarTodas();
    }

    public function buscar($id) {
        return $this->model->buscarPorId($id);
    }

    public function executar($veiculo_id, $itens_solicitados, $max_lastros = 2, $obs = '') {
        return $this->model->executar($veiculo_id, $itens_solicitados, $max_lastros, $obs);
    }

    public function excluir($id) {
        return $this->model->excluir($id);
    }
}
