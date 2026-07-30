<?php
require_once __DIR__ . '/../models/Simulacao.php';

class SimulacaoController {
    private Simulacao $model;

    public function __construct() {
        $this->model = new Simulacao();
    }

    public function listar(): array {
        return $this->model->listarTodas();
    }

    public function buscar(int $id): ?array {
        return $this->model->buscarPorId($id);
    }

    public function executar(int $pedidoId, array $frotaSelecionada, string $observacoes = ''): array {
        return $this->model->executar($pedidoId, $frotaSelecionada, $observacoes);
    }

    public function executarPedidos(array $pedidoIds, array $frotaSelecionada, string $observacoes = ''): array {
        return $this->model->executarPedidos($pedidoIds, $frotaSelecionada, $observacoes);
    }

    public function executarManual(int $pedidoId, array $frotaSelecionada, array $placements, string $observacoes = ''): array {
        return $this->model->executarManual($pedidoId, $frotaSelecionada, $placements, $observacoes);
    }

    public function executarManualPedidos(array $pedidoIds, array $frotaSelecionada, array $placements, string $observacoes = ''): array {
        return $this->model->executarManualPedidos($pedidoIds, $frotaSelecionada, $placements, $observacoes);
    }

    public function atualizarMontagemManual(int $simulacaoId, int $simulacaoVeiculoId, array $itens): array {
        return $this->model->atualizarMontagemManual($simulacaoId, $simulacaoVeiculoId, $itens);
    }

    public function excluir(int $id): bool {
        return $this->model->excluir($id);
    }
}
