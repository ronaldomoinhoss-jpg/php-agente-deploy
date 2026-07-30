<?php
require_once __DIR__ . '/../models/Rota.php';

class RotaController {
    private Rota $model;

    public function __construct() {
        $this->model = new Rota();
    }

    public function listar(): array {
        return $this->model->listarTodas();
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
}
