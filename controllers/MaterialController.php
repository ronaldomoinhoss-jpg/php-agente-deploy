<?php
require_once __DIR__ . '/../models/Material.php';

class MaterialController {
    private Material $model;

    public function __construct() {
        $this->model = new Material();
    }

    public function listar(string $busca = '', string $categoria = ''): array {
        return $this->model->listarTodos($busca, $categoria);
    }

    public function listarCategorias(): array {
        return $this->model->listarCategorias();
    }

    public function buscar(int $id): ?array {
        return $this->model->buscarPorId($id);
    }

    public function buscarPorCodigo(string $codigo): ?array {
        return $this->model->buscarPorCodigo($codigo);
    }

    public function salvar(array $data): int {
        return $this->model->salvar($data);
    }

    public function excluir(int $id): bool {
        return $this->model->excluir($id);
    }
}

