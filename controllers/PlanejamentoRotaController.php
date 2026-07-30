<?php
require_once __DIR__ . '/../models/PlanejamentoRota.php';

class PlanejamentoRotaController {
    private PlanejamentoRota $model;

    public function __construct() {
        $this->model = new PlanejamentoRota();
    }

    public function listar(?string $dataInicio = null, ?string $dataFim = null): array {
        return $this->model->listarTodos($dataInicio, $dataFim);
    }

    public function buscar(int $id): ?array {
        return $this->model->buscarPorId($id);
    }

    public function gerar(array $data): array {
        return $this->model->gerar($data);
    }

    public function listarSemana(string $dataReferencia): array {
        return $this->model->listarSemana($dataReferencia);
    }
}
