<?php
require_once __DIR__ . '/../models/BaseOperacional.php';

class BaseController {
    private BaseOperacional $model;

    public function __construct() {
        $this->model = new BaseOperacional();
    }

    public function listar(): array {
        return $this->model->listarTodas();
    }

    public function buscar(int $id): ?array {
        return $this->model->buscarPorId($id);
    }

    public function buscarPorCodigoOuNome(string $valor): ?array {
        return $this->model->buscarPorCodigoOuNome($valor);
    }

    public function salvar(array $data): int {
        return $this->model->salvar($data);
    }

    public function excluir(int $id): bool {
        return $this->model->excluir($id);
    }
}

