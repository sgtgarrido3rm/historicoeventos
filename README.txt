CONTROLE DE EVENTOS - PHP + MYSQLI + ADMINLTE

REQUISITOS
- PHP 8.0 ou superior
- MySQL 5.7+ ou MariaDB compatível
- Apache/Nginx com PHP habilitado
- Acesso à internet para carregar AdminLTE, Bootstrap, Font Awesome e DataTables via CDN

INSTALAÇÃO
1. Copie a pasta controle_eventos para a pasta pública do servidor (por exemplo: /var/www/html/controle_eventos).
2. Importe database.sql no MySQL:
   mysql -u root -p < database.sql
   Ou importe o arquivo pelo phpMyAdmin.
3. Edite config.php e informe host, banco, usuário e senha do MySQL.
4. Acesse no navegador:
   http://localhost/controle_eventos/

COMO FUNCIONA
- Cadastre a data em que ocorreu cada evento.
- A listagem mostra a data anterior, a data do evento e a diferença em dias corridos.
- O primeiro evento não possui data anterior, por isso aparece como "Primeiro evento".
- Os registros são exibidos do mais recente para o mais antigo.
- A próxima data cadastrada passa a usar a data do evento mais recente como sua data anterior.
- Para preservar a sequência cronológica, o sistema não permite cadastrar uma data igual ou anterior à última data registrada.
- É possível excluir um registro. A diferença dos demais registros é recalculada automaticamente, pois é obtida a partir das datas armazenadas.

OBSERVAÇÕES
- A diferença é calculada pelo MySQL com DATEDIFF, considerando dias corridos e sem contar a hora.
- O sistema usa MySQLi com prepared statements, sem PDO.
- O formulário possui token CSRF básico.
- Em produção, configure usuário de banco com permissões apenas para este banco e habilite HTTPS.
