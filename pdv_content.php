<?php if (!empty($pdv['equipment'])): ?>
    <section class="equipment-section">
        <h4>Equipamentos</h4>
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Número de Série</th>
                    <th>Status</th>
                    <th>Cartões de Internet</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pdv['equipment'] as $equipment): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($equipment['type']); ?></td>
                        <td><?php echo htmlspecialchars($equipment['serial_number']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $equipment['status']; ?>">
                                <?php
                                switch ($equipment['status']) {
                                    case 'active':
                                        echo 'Em Uso';
                                        break;
                                    case 'inactive':
                                        echo 'Inativo';
                                        break;
                                    default:
                                        echo ucfirst($equipment['status']);
                                }
                                ?>
                            </span>
                        </td>                        <td>
                            <?php if ($equipment['internet_card']): ?>
                                <?php echo htmlspecialchars($equipment['internet_card_id'] . ' - ' . $equipment['internet_card']); ?>
                                <a href="internet_card_form.php?id=<?php echo $equipment['internet_card_id']; ?>&event_id=<?php echo $event_id; ?>&pos_id=<?php echo $pos_id; ?><?php echo isset($_GET['category']) ? '&category='.urlencode($_GET['category']) : ''; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?>" style="text-decoration: none; margin-left: 5px;" title="Editar cartão de internet">✏️</a>                                <?php if ($equipment['internet_card_phone']): ?>
                                    [<?php echo htmlspecialchars($equipment['internet_card_phone']); ?>]
                                <?php endif; ?>
                                <?php if ($equipment['internet_card_pin']): ?>
                                    | PIN: <?php echo htmlspecialchars($equipment['internet_card_pin']); ?>
                                <?php endif; ?>
                                <?php if ($equipment['internet_card_puk']): ?>
                                    | PUK: <?php echo htmlspecialchars($equipment['internet_card_puk']); ?>
                                <?php endif; ?>
                                <?php if ($equipment['internet_card_status']): ?>
                                    <span class="status-badge status-<?php echo $equipment['internet_card_status']; ?>">
                                        <?php echo $equipment['internet_card_status'] === 'active' ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                <?php endif; ?>
                                <div class="action-buttons">
                                    <a href="equipment_internet_form.php?id=<?php echo $equipment['id']; ?>&event_id=<?php echo $event_id; ?>&pos_id=<?php echo $pos_id; ?>" class="btn btn-edit">Editar</a>
                                    <button class="btn btn-remove" onclick="removeInternetCard(<?php echo $equipment['id']; ?>, event)">Remover</button>
                                </div>
                            <?php else: ?>
                                <a href="equipment_internet_form.php?id=<?php echo $equipment['id']; ?>&event_id=<?php echo $event_id; ?>&pos_id=<?php echo $pos_id; ?>" class="btn btn-edit">Adicionar</a>
                            <?php endif; ?>
                        </td>
                        <td class="action-buttons">
                            <a href="equipment_form.php?id=<?php echo $equipment['id']; ?>&event_id=<?php echo $event_id; ?>" class="btn btn-edit">Editar</a>
                            <button class="btn btn-remove" onclick="showModal('remove', <?php echo $equipment['id']; ?>)">Remover</button>
                            <button class="btn btn-delete" onclick="showModal('delete', <?php echo $equipment['id']; ?>)">Excluir</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php else: ?>
    <section class="equipment-section">
        <h4>Equipamentos</h4>
        <p>Nenhum equipamento associado a este PDV.</p>
    </section>
<?php endif; ?>

<section class="users-section">
    <h4>Usuários</h4>
    <?php if (!empty($pdv['users'])): ?>
        <table>
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Senha</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pdv['users'] as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['password']); ?></td>
                        <td class="action-buttons">
                            <a href="users.php?id=<?php echo $user['id']; ?>&event_id=<?php echo $event_id; ?>" class="btn btn-edit">Editar</a>
                            <button class="btn btn-remove" onclick="removeUser(<?php echo $pos_id; ?>, <?php echo $user['id']; ?>)">Remover</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top: 10px;">
            <button class="btn btn-primary" onclick="addUser(<?php echo $pos_id; ?>)">+ Adicionar Usuário</button>
        </div>
    <?php else: ?>
        <p>Nenhum usuário associado a este PDV.</p>
        <div style="margin-top: 10px;">
            <button class="btn btn-primary" onclick="addUser(<?php echo $pos_id; ?>)">+ Adicionar Usuário</button>
        </div>
    <?php endif; ?>
</section>

<section class="extras-section">
    <h4>Extras</h4>
    <?php if (!empty($pdv['extras'])): ?>
        <?php
        // Group extras by category
        $extrasByCategory = [];
        foreach ($pdv['extras'] as $extra) {
            $categoryId = $extra['category_id'] ?? 0;
            $categoryName = $extra['category_name'] ?? 'Sem Categoria';
            if (!isset($extrasByCategory[$categoryId])) {
                $extrasByCategory[$categoryId] = [
                    'name' => $categoryName,
                    'items' => []
                ];
            }
            $extrasByCategory[$categoryId]['items'][] = $extra;
        }
        
        // Sort categories so "Sem Categoria" appears last
        uksort($extrasByCategory, function($a, $b) {
            if ($a === 0) return 1;
            if ($b === 0) return -1;
            return $a - $b;
        });
        ?>
        
        <?php foreach ($extrasByCategory as $categoryId => $category): ?>
            <div class="extras-category">
                <h5><?php echo htmlspecialchars($category['name']); ?></h5>
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Preço</th>
                            <th>Taxa</th>
                            <th>Tipo</th>
                            <th>TOC Item Code</th>
                            <th>TOC Item ID</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($category['items'] as $extra): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($extra['display_name']); ?></td>
                                <td><?php echo number_format($extra['price'], 2); ?>€</td>
                                <td><?php echo htmlspecialchars($extra['tax_type']); ?>%</td>
                                <td><?php echo htmlspecialchars($extra['type'] === 'product' ? 'Produto' : 'Serviço'); ?></td>
                                <td><?php echo htmlspecialchars($extra['toconline_item_code'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($extra['toconline_item_id'] ?: '-'); ?></td>
                                <td class="action-buttons">
                                    <a href="criar_extra.php?id=<?php echo $extra['id']; ?>&event_id=<?php echo $event_id; ?>&pos_id=<?php echo $pos_id; ?>" class="btn btn-edit">Editar</a>
                                    <button class="btn btn-remove" onclick="removeExtra(<?php echo $extra['id']; ?>, <?php echo $pos_id; ?>)">Remover</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
        
        <div style="margin-top: 10px;">
            <a href="criar_extra.php?event_id=<?php echo $event_id; ?>&pos_id=<?php echo $pos_id; ?>" class="btn btn-primary">+ Adicionar Extra</a>
        </div>
    <?php else: ?>
        <p>Nenhum extra associado a este PDV.</p>
        <div style="margin-top: 10px;">
            <a href="criar_extra.php?event_id=<?php echo $event_id; ?>&pos_id=<?php echo $pos_id; ?>" class="btn btn-primary">+ Adicionar Extra</a>
        </div>
    <?php endif; ?>
</section>
