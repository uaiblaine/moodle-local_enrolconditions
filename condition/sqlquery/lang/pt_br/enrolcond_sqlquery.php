<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Strings for component 'enrolcond_sqlquery', language 'pt_br'.
 *
 * @package    enrolcond_sqlquery
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['cachedef_results'] = 'Nomes de usuário devolvidos pelas consultas';
$string['cachettl'] = 'Manter os resultados por';
$string['cachettl_desc'] = 'Por quanto tempo os nomes de usuário devolvidos por uma consulta são reaproveitados antes que a consulta rode de novo. Um tempo maior conta como um dia, e 0 roda a consulta a cada verificação. Quando o tempo acaba, uma requisição roda a consulta de novo enquanto as outras continuam usando o resultado anterior.';
$string['desc'] = 'Você está na lista "{$a}"';
$string['descmissing'] = 'Você está numa lista que não existe mais';
$string['errorbusy'] = 'A lista está sendo lida por outra requisição; a condição fica indeterminada até que ela esteja pronta.';
$string['errorcomment'] = 'A consulta não pode conter comentários (--, /* ou #).';
$string['errorforbidden'] = 'A consulta não pode conter "{$a}".';
$string['errormissing'] = 'A consulta {$a} não está mais definida.';
$string['errorquery'] = 'Consulta inválida: {$a}';
$string['errorrun'] = 'A consulta falhou: {$a}';
$string['errorselect'] = 'A consulta deve começar com SELECT ou WITH.';
$string['errorsemicolon'] = 'A consulta deve ser um único comando, sem ";".';
$string['errortransaction'] = 'A lista não é executada dentro de uma transação do banco de dados; a condição fica indeterminada até que a lista seja lida fora de uma.';
$string['invalidquery'] = 'Escolha uma lista.';
$string['noqueries'] = 'O administrador ainda não definiu nenhuma lista.';
$string['pluginname'] = 'Lista de uma consulta';
$string['privacy:metadata'] = 'A condição de inscrição Lista de uma consulta não guarda nenhum dado pessoal. As consultas leem dados guardados por outros componentes.';
$string['query'] = 'Lista';
$string['query_help'] = 'A lista dos usuários que atendem à condição, escolhida entre as listas que os administradores definiram.

* Um usuário atende à condição quando o seu nome de usuário está na lista. Maiúsculas e minúsculas são iguais.
* Cada lista é uma consulta ao banco de dados que só os administradores escrevem, e eles podem alterá-la ou removê-la a qualquer momento. Todo professor que pode configurar este método de inscrição pode usar todas as listas, e o resultado da condição mostra a ele se um usuário está nela.
* O resultado de uma lista é reaproveitado pelo tempo definido pelos administradores (5 minutos por padrão, no máximo um dia), de modo que uma mudança nos dados pode levar esse tempo para contar.
* Uma lista removida ou desabilitada, ou que falha ao rodar, não pode ser avaliada: as novas inscrições são recusadas, e ninguém já inscrito é suspenso, removido ou reativado por causa dela.

**Exemplo**: "Servidores da Escola de Enfermagem", uma lista que os administradores montaram a partir do cadastro de pessoal, para um curso aberto só a essa escola.

Uma lista que muda não verifica ninguém de novo por si só: para acompanhar as suas mudanças, "Verificar as condições" precisa incluir "Quando o usuário entra no site" ou "Periodicamente, na tarefa agendada".';
$string['queryheading'] = 'Lista {$a}';
$string['queryname'] = 'Nome';
$string['queryname_desc'] = 'Nome mostrado aos professores e aos usuários que não atendem à condição. Deixe-o vazio para desabilitar a lista.';
$string['querysql'] = 'Consulta';
$string['querysql_desc'] = 'Um único SELECT (ou WITH ... SELECT) que devolve uma coluna chamada "username". Use {table} para os nomes das tabelas. :courseid é o curso do método de inscrição. Comandos que alteram dados, comentários e ";" não são aceitos; a consulta roda uma vez ao ser salva. Esta verificação protege contra enganos, não é uma barreira de segurança: ela não consegue provar que uma consulta apenas lê.';
$string['settingsintro'] = 'Só os administradores definem estas listas. As consultas rodam com as permissões de banco de dados do Moodle e podem ler qualquer tabela: revise-as como você revisaria código. Todo professor que pode configurar um método de inscrição com condições pode usar todas as listas, e o resultado de uma condição mostra a ele se um usuário está nela.';
$string['slots'] = 'Número de listas';
$string['slots_desc'] = 'Quantas listas podem ser definidas abaixo, até {$a}.';
