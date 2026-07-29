<?php
require_once __DIR__ . '/../models/PedidoCarga.php';

class PedidoCargaController {
    private PedidoCarga $model;

    public function __construct() {
        $this->model = new PedidoCarga();
    }

    public function listar(): array {
        return $this->model->listarTodos();
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
