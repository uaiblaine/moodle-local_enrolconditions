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
 * Strings for local_enrolconditions.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['cachedef_observedevents'] = 'Eventos observados pelas condições das instâncias de inscrição que verificam novamente quando algo muda';
$string['checkbatchsize'] = 'Inscrições por lote';
$string['checkbatchsize_desc'] = 'Quantas inscrições a tarefa agendada lê e verifica de novo de cada vez. Cada execução verifica lotes até esgotar o seu tempo limite, e a execução seguinte continua de onde ela parou.';
$string['checkconditionstask'] = 'Verificar novamente as condições das inscrições existentes';
$string['checkmodeenrol'] = 'Quando o usuário se inscreve (sempre)';
$string['checkmodeevents'] = 'Quando algo de que as condições dependem muda';
$string['checkmodelogin'] = 'Quando o usuário entra no site';
$string['checkmodeperiodic'] = 'Periodicamente, na tarefa agendada';
$string['checkmodes'] = 'Verificar as condições';
$string['checkmodes_help'] = 'Quando as condições são verificadas. Elas são sempre verificadas quando um usuário se inscreve; as inscrições que já existem são verificadas de novo só nos momentos marcados aqui.

* **Quando o usuário se inscreve (sempre)**: quem não atende às condições não consegue se inscrever. Não pode ser desligado.
* **Quando o usuário entra no site**: as inscrições do usuário por este método são verificadas de novo durante a sua entrada no site.
* **Periodicamente, na tarefa agendada**: a tarefa "Verificar novamente as condições das inscrições existentes", uma vez por hora por padrão, percorre as inscrições aos poucos, por um tempo limitado a cada execução. Num site grande, uma volta completa leva várias execuções; "Tempo limite de uma verificação periódica" e "Inscrições por lote", na administração do site, decidem quantas são verificadas por execução.
* **Quando algo de que as condições dependem muda**: as inscrições do usuário são verificadas de novo em segundo plano, logo depois da mudança, e não na mesma requisição que a fez. Só quatro condições reagem: "Perfil do usuário" (o perfil é atualizado), "Coorte" (um membro é adicionado ou removido), "Curso concluído" (o curso é concluído) e "Inscrito em um curso" (uma inscrição é criada, alterada ou removida). Uma data que passa, ou uma lista ou um arquivo de nomes de usuário que muda, não.

**Quando usar cada uma**

* Condições sobre coortes, campos do perfil ou outros cursos, e o usuário deve perder o acesso logo depois de sair da coorte: quando algo de que as condições dependem muda.
* Uma condição "Data" com fim, ou uma "Lista de uma consulta" cujos dados mudam: periodicamente, na tarefa agendada, ou quando o usuário entra no site.
* As condições só filtram quem entra, e ninguém já inscrito deve ser afetado: deixe as outras caixas desmarcadas.

**Exemplo**: uma condição "Coorte" com "Estagiários 2027", com quando algo de que as condições dependem muda e periodicamente marcadas: um estagiário removido da coorte é suspenso logo depois, e a tarefa periódica também pega as mudanças que não geram evento.

Alterar as condições não verifica ninguém na hora: as inscrições existentes são verificadas contra as novas condições no próximo dos momentos marcados aqui. O que acontece quando uma verificação falha é definido em "Quando as condições deixarem de ser atendidas".';
$string['checktimelimit'] = 'Tempo limite de uma verificação periódica';
$string['checktimelimit_desc'] = 'Por quanto tempo cada execução da tarefa agendada continua verificando inscrições. Nenhum lote novo começa depois desse tempo, de modo que uma execução sempre verifica pelo menos um lote; ela para antes quando já percorreu todas as inscrições, e a execução seguinte continua de onde ela parou.';
$string['conditionaction'] = 'Quando as condições deixarem de ser atendidas';
$string['conditionaction_help'] = 'O que acontece com uma inscrição feita por este método quando uma nova verificação constata que o usuário não atende mais às condições.

* **Nada (apenas registrar)**: a inscrição fica como está. A falha é registrada nos logs uma vez, e de novo só depois que o usuário voltar a atender às condições nesse meio-tempo.
* **Suspender a inscrição**: o usuário não consegue mais entrar no curso, e o seu trabalho nele é mantido. Com "Reativar quando as condições voltarem a ser atendidas" marcada, a inscrição volta sozinha. Uma inscrição que outra pessoa já tinha suspendido fica como está.
* **Remover a inscrição**: a inscrição do usuário é cancelada, e alguns dos seus dados no curso, como a participação em grupos, são apagados. As condições nunca a trazem de volta.

**Quando usar cada uma**

* Você quer ver quem deixou de se qualificar antes que algo aconteça com essas pessoas: nada (apenas registrar).
* O acesso acompanha as condições, e quem voltar a se qualificar deve encontrar o seu trabalho onde o deixou: suspender a inscrição.
* As vagas são limitadas, e quem deixa de se qualificar deve liberar a sua: remover a inscrição.

**Exemplo**: suspender a inscrição, com "Reativar quando as condições voltarem a ser atendidas" marcada, num curso aberto aos membros de uma coorte: um membro removido da coorte perde o acesso, e o recupera com o seu trabalho ao voltar para ela.

Sem efeito a menos que "Verificar as condições" tenha uma caixa marcada além de "Quando o usuário se inscreve (sempre)". Quando o resultado depende de uma condição que não pode ser avaliada (um tipo de condição desabilitado ou desinstalado, uma lista ou um arquivo ausente, uma lista que não pôde ser lida), a inscrição nunca muda. Alterar esta configuração não desfaz o que já foi feito.';
$string['conditionactionnone'] = 'Nada (apenas registrar)';
$string['conditionactionremove'] = 'Remover a inscrição';
$string['conditionactionsuspend'] = 'Suspender a inscrição';
$string['conditiondisabled'] = 'Condição "{$a}" desabilitada.';
$string['conditionenabled'] = 'Condição "{$a}" habilitada.';
$string['conditionnotinstalled'] = 'A condição "{$a}" não está instalada.';
$string['conditionreactivate'] = 'Reativar quando as condições voltarem a ser atendidas';
$string['conditionreactivate_help'] = 'Se uma inscrição que as condições suspenderam volta sozinha quando o usuário volta a atender às condições.

* Só as suspensões feitas por este método são desfeitas. Uma inscrição que um professor, outro plugin ou uma expiração suspendeu continua suspensa.
* Uma suspensão deixa de ser desfeita quando outra pessoa altera essa inscrição, por exemplo reativando-a ou alterando-a diretamente no banco de dados. Alterar só as datas, enquanto ela continua suspensa, a mantém.
* Acontece numa nova verificação, nos momentos marcados em "Verificar as condições", nunca na hora.
* Remover todas as condições conta como atendê-las: as suspensões que elas fizeram são desfeitas na próxima verificação.
* Marcar esta opção mais tarde também desfaz as suspensões feitas enquanto ela estava desmarcada.

**Exemplo**: marcada, com "Suspender a inscrição", num curso aberto ao departamento "Enfermagem": um usuário que muda de departamento é suspenso, e recupera o acesso e o seu trabalho ao voltar para Enfermagem.

Só faz diferença com "Suspender a inscrição": uma inscrição removida nunca volta.';
$string['editoraddcondition'] = 'Adicionar condição';
$string['editoraddgroup'] = 'Adicionar grupo';
$string['editorall'] = 'Todas estas';
$string['editorany'] = 'Qualquer uma destas';
$string['editorchoosetype'] = 'Tipo de condição';
$string['editoredit'] = 'Editar';
$string['editorempty'] = 'Nenhuma condição.';
$string['editornoscript'] = 'O editor de condições precisa de JavaScript. As condições salvas são mantidas sem alteração.';
$string['editornot'] = 'Não deve ser atendida';
$string['editornotavailable'] = 'Esta condição se refere a algo que você não pode usar neste curso.';
$string['editoroperator'] = 'Como combinar os itens deste grupo';
$string['editorremove'] = 'Remover';
$string['editorremovegroup'] = 'Remover grupo';
$string['editorunknowntype'] = 'O tipo de condição "{$a}" não está instalado. A autoinscrição é recusada enquanto ele estiver em uso.';
$string['eventconditionsnotmet'] = 'Condições de inscrição não mais atendidas';
$string['eventuserenrolmentreactivated'] = 'Inscrição de usuário reativada pelas condições';
$string['eventuserenrolmentsuspended'] = 'Inscrição de usuário suspensa pelas condições';
$string['failuredescription'] = 'Uma condição não foi atendida.';
$string['failurenot'] = 'Você não deve atender a esta condição: {$a}';
$string['failurenotgroup'] = 'Você atende a um grupo de condições que não deve ser atendido.';
$string['failureoneof'] = 'Você deve atender a pelo menos uma das seguintes:';
$string['failureunknown'] = 'Uma condição não pode mais ser verificada. Entre em contato com o professor do curso.';
$string['manageconditions'] = 'Gerenciar condições';
$string['pluginname'] = 'Condições de inscrição';
$string['privacy:metadata:state'] = 'Ações tomadas quando as condições de uma inscrição deixaram de ser atendidas. As inscrições são guardadas pelo núcleo do Moodle.';
$string['privacy:metadata:state:action'] = 'Ação tomada: nenhuma (apenas registrada) ou suspensão.';
$string['privacy:metadata:state:closereason'] = 'Por que o registro deixou de valer.';
$string['privacy:metadata:state:enrol'] = 'O método de inscrição.';
$string['privacy:metadata:state:reason'] = 'Condições que não foram atendidas.';
$string['privacy:metadata:state:timeclosed'] = 'Quando o registro deixou de valer.';
$string['privacy:metadata:state:timereactivated'] = 'Quando a inscrição foi reativada.';
$string['privacy:metadata:state:timesuspended'] = 'Quando a ação foi tomada.';
$string['privacy:metadata:state:userenrolmentid'] = 'A inscrição do usuário.';
$string['privacy:path:state'] = 'Ações das condições';
$string['purgeorphanstatestask'] = 'Excluir os registros de condições de inscrições que não existem mais';
$string['rechecktask'] = 'Verificar novamente as inscrições de um usuário depois de uma mudança de que as condições dependem';
$string['settings'] = 'Novas verificações';
$string['subplugintype_enrolcond'] = 'Condição';
$string['subplugintype_enrolcond_plural'] = 'Condições';
$string['treebadnode'] = 'Nó inválido na posição {$a}.';
$string['treeconditionerror'] = '{$a->name}: {$a->error}';
$string['treeinvalid'] = 'Árvore de condições inválida: {$a}';
$string['treeinvalidjson'] = 'As condições não são um JSON válido.';
$string['treeroot'] = 'raiz';
$string['treerootnotgroup'] = 'A raiz das condições deve ser um grupo.';
$string['treetoodeep'] = 'Os grupos podem ser aninhados em no máximo {$a} níveis.';
$string['treetoomanynodes'] = 'São permitidos no máximo {$a} condições e grupos.';
$string['treeunknowntype'] = 'O tipo de condição "{$a}" não está disponível.';
