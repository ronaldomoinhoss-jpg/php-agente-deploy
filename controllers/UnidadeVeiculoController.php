<?php
require_once __DIR__ . '/../models/UnidadeVeiculo.php';

class UnidadeVeiculoController {
    private UnidadeVeiculo $model;

    public function __construct() {
        $this->model = new UnidadeVeiculo();
    }

    public function listar(bool $somenteAtivas = false): array {
        return $this->model->listarTodas($somenteAtivas);
    }

    public function buscar(int $id): ?array {
        return $this->model->buscarPorId($id);
    }

    public function salvar(array $data): int {
        return $this->model->salvar($data);
    }

    public function excluir(int $id): bool {
        return $this->model->excluir($id);
    }

    public function listarDisponiveisNaData(string $dataOperacao): array {
        return $this->model->listarDisponiveisNaData($dataOperacao);
    }
}
