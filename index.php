<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensagem = '';
$tipoMensagem = 'success';

function h(?string $valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function dataBr(?string $data): string {
    if (!$data) return '—';
    $dt = DateTime::createFromFormat('!Y-m-d', $data);
    return $dt ? $dt->format('d/m/Y') : '—';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $mensagem = 'A solicitação expirou. Atualize a página e tente novamente.';
        $tipoMensagem = 'danger';
    } else {
        $acao = $_POST['acao'] ?? '';

        if ($acao === 'inserir') {
            $dataEvento = trim($_POST['data_evento'] ?? '');
            $dt = DateTime::createFromFormat('!Y-m-d', $dataEvento);
            $valida = $dt && $dt->format('Y-m-d') === $dataEvento;

            if (!$valida) {
                $mensagem = 'Informe uma data válida.';
                $tipoMensagem = 'danger';
            } else {
                try {
                    // A nova data deve ser posterior ao último evento cadastrado.
                    $resultado = $conn->query("SELECT MAX(data_evento) AS ultima_data FROM eventos");
                    $ultima = $resultado->fetch_assoc()['ultima_data'] ?? null;

                    if ($ultima !== null && $dataEvento <= $ultima) {
                        $mensagem = 'A nova data deve ser posterior ao último evento (' . dataBr($ultima) . ').';
                        $tipoMensagem = 'warning';
                    } else {
                        $stmt = $conn->prepare("INSERT INTO eventos (data_evento) VALUES (?)");
                        $stmt->bind_param('s', $dataEvento);
                        $stmt->execute();
                        $mensagem = 'Data do evento cadastrada com sucesso.';
                    }
                } catch (mysqli_sql_exception $e) {
                    $mensagem = 'Não foi possível cadastrar a data. Verifique se ela já está registrada.';
                    $tipoMensagem = 'danger';
                }
            }
        } elseif ($acao === 'excluir') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt = $conn->prepare("DELETE FROM eventos WHERE id = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $mensagem = 'Registro excluído.';
            } else {
                $mensagem = 'Registro inválido.';
                $tipoMensagem = 'danger';
            }
        }
    }
}

// Lista os eventos em ordem cronológica e calcula a diferença para o evento anterior.
$sql = "
    SELECT
        atual.id,
        atual.data_evento,
        anterior.data_evento AS data_anterior,
        CASE
            WHEN anterior.data_evento IS NULL THEN NULL
            ELSE DATEDIFF(atual.data_evento, anterior.data_evento)
        END AS dias_transcorridos
    FROM eventos AS atual
    LEFT JOIN eventos AS anterior
        ON anterior.data_evento = (
            SELECT MAX(e2.data_evento)
            FROM eventos AS e2
            WHERE e2.data_evento < atual.data_evento
        )
    ORDER BY atual.data_evento DESC, atual.id DESC
";
$resultado = $conn->query($sql);
$eventos = $resultado->fetch_all(MYSQLI_ASSOC);

$totalEventos = count($eventos);
$ultimaData = $totalEventos > 0 ? $eventos[0]['data_evento'] : null;
$diasDesdeUltimoEvento = $ultimaData !== null
    ? (int)(new DateTimeImmutable($ultimaData))
        ->diff(new DateTimeImmutable('today'))
        ->format('%a')
    : null;

// Maior intervalo registrado, desconsiderando o primeiro evento.
$maiorIntervalo = 0;
foreach ($eventos as $evento) {
    if ($evento['dias_transcorridos'] !== null) {
        $maiorIntervalo = max($maiorIntervalo, (int)$evento['dias_transcorridos']);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Controle de Eventos</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <span class="nav-link">Controle de Eventos</span>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="index.php" class="brand-link">
            <i class="fas fa-calendar-days ml-3 mr-2"></i>
            <span class="brand-text font-weight-light">Controle de Eventos</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" role="menu">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link active">
                            <i class="nav-icon fas fa-table"></i>
                            <p>Listagem de datas</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <h1 class="m-0">Histórico de eventos</h1>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">

                <?php if ($mensagem !== ''): ?>
                    <div class="alert alert-<?= h($tipoMensagem) ?> alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <?= h($mensagem) ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-4 col-12">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3><?= $totalEventos ?></h3>
                                <p>Eventos cadastrados</p>
                            </div>
                            <div class="icon"><i class="fas fa-calendar-check"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-12">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3><?= $ultimaData ? dataBr($ultimaData) : '—' ?></h3>
                                <p>Data do último evento</p>
                            </div>
                            <div class="icon"><i class="fas fa-calendar-day"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-12">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3><?= $maiorIntervalo ?> <sup style="font-size: 18px">dias</sup></h3>
                                <p>Maior intervalo entre eventos</p>
                            </div>
                            <div class="icon"><i class="fas fa-clock"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-12">
                        <div class="small-box bg-danger">
                            <div class="inner">
                                <h3><?= $diasDesdeUltimoEvento ?> <sup style="font-size: 18px">dias</sup></h3>
                                <p>Desde o último evento</p>
                            </div>
                            <div class="icon"><i class="fas fa-hourglass"></i></div>
                        </div>
                    </div>
                </div>

                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-plus-circle mr-1"></i> Registrar novo evento</h3>
                    </div>
                    <form method="post" action="index.php">
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="acao" value="inserir">
                        <div class="card-body">
                            <div class="form-row align-items-end">
                                <div class="form-group col-md-4 mb-md-0">
                                    <label for="data_evento">Data em que ocorreu o evento</label>
                                    <input
                                        type="date"
                                        class="form-control"
                                        id="data_evento"
                                        name="data_evento"
                                        required
                                        max="<?= date('Y-m-d') ?>"
                                    >
                                </div>
                                <div class="form-group col-md-4 mb-md-0">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save mr-1"></i> Cadastrar data
                                    </button>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">
                                        A data informada precisa ser posterior à data do último evento cadastrado.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-list mr-1"></i> Histórico e intervalo de dias</h3>
                    </div>
                    <div class="card-body">
                        <table id="tabelaEventos" class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 8%">Nº</th>
                                    <th>Data anterior</th>
                                    <th>Data do evento</th>
                                    <th>Diferença (dias)</th>
                                    <th style="width: 10%">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($eventos as $indice => $evento): ?>
                                <tr>
                                    <td><?= $totalEventos - $indice ?></td>
                                    <td><?= h(dataBr($evento['data_anterior'])) ?></td>
                                    <td><?= h(dataBr($evento['data_evento'])) ?></td>
                                    <td>
                                        <?php if ($evento['dias_transcorridos'] === null): ?>
                                            <span class="badge badge-secondary">Primeiro evento</span>
                                        <?php else: ?>
                                            <span class="badge badge-info">
                                                <?= (int)$evento['dias_transcorridos'] ?> dias
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <form method="post" action="index.php" onsubmit="return confirm('Confirma a exclusão deste evento?');">
                                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="acao" value="excluir">
                                            <input type="hidden" name="id" value="<?= (int)$evento['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Excluir">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($totalEventos === 0): ?>
                            <div class="text-center text-muted py-3">Nenhum evento cadastrado até o momento.</div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <footer class="main-footer">
        <strong>Controle de Eventos</strong>
        <div class="float-right d-none d-sm-inline-block">Início em 22/07/2023</div>
    </footer>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>
<script>
$(function () {
    $('#tabelaEventos').DataTable({
        responsive: true,
        autoWidth: false,
        order: [],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/pt-BR.json'
        },
        columnDefs: [
            { orderable: false, targets: 4 }
        ]
    });
});
</script>
</body>
</html>
