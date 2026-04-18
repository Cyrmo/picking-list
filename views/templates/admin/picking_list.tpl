{**
 * DFS Picking List — Template Back-Office
 * v1.1.0 — Filtre état de commande + filtres mode OU
 *
 * @author    Cyrille Mohr - Digital Food System <contact@digitaifoodsystem.com>
 *}

<style>
/* ============================================================
   DFS Picking List — Styles BO
   ============================================================ */

.dfspl-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
    padding: 20px 24px;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    border-radius: 8px;
    color: #fff;
}

.dfspl-header .dfspl-icon { font-size: 32px; opacity: 0.9; }
.dfspl-header h1 { font-size: 22px; font-weight: 700; margin: 0; color: #fff; letter-spacing: 0.3px; }
.dfspl-header .dfspl-subtitle { font-size: 13px; color: rgba(255,255,255,0.65); margin: 2px 0 0; }

/* Panneau filtre */
.dfspl-filter-panel {
    background: #fff;
    border: 1px solid #e0e4ea;
    border-radius: 8px;
    padding: 20px 24px;
    margin-bottom: 20px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}

.dfspl-filter-panel .panel-title {
    font-size: 13px;
    font-weight: 600;
    color: #555;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 0 0 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.dfspl-form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: flex-end;
}

.dfspl-form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.dfspl-form-group label {
    font-size: 12px;
    font-weight: 600;
    color: #444;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.dfspl-form-group select,
.dfspl-form-group input[type="date"] {
    height: 38px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    padding: 0 12px;
    font-size: 13px;
    color: #333;
    background: #fff;
    transition: border-color 0.2s;
    min-width: 220px;
}

.dfspl-form-group select:focus,
.dfspl-form-group input[type="date"]:focus {
    outline: none;
    border-color: #0f3460;
    box-shadow: 0 0 0 3px rgba(15, 52, 96, 0.12);
}

/* Multi-select état de commande */
.dfspl-states-wrapper {
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex: 1;
    min-width: 260px;
    max-width: 340px;
}

.dfspl-states-wrapper label {
    font-size: 12px;
    font-weight: 600;
    color: #444;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.dfspl-states-hint {
    font-size: 11px;
    color: #999;
    font-style: italic;
    font-weight: 400;
    text-transform: none;
}

select[name="picking_states[]"] {
    height: 120px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    padding: 4px 8px;
    font-size: 12px;
    color: #333;
    background: #fff;
    width: 100%;
    transition: border-color 0.2s;
    cursor: pointer;
}

select[name="picking_states[]"]:focus {
    outline: none;
    border-color: #0f3460;
    box-shadow: 0 0 0 3px rgba(15, 52, 96, 0.12);
}

select[name="picking_states[]"] option {
    padding: 3px 6px;
}

select[name="picking_states[]"] option:checked {
    background: #0f3460;
    color: #fff;
}

/* Séparateur entre blocs de filtres */
.dfspl-filter-separator {
    width: 100%;
    height: 1px;
    background: #eee;
    margin: 12px 0 4px;
}

.dfspl-filter-row-label {
    width: 100%;
    font-size: 11px;
    font-weight: 600;
    color: #aaa;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: -4px;
}

/* Boutons */
.dfspl-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    border-radius: 6px;
    border: none;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.dfspl-btn:active { transform: scale(0.98); }
.dfspl-btn-primary { background: #0f3460; color: #fff; }
.dfspl-btn-primary:hover { background: #1a4a80; }
.dfspl-btn-success { background: #1e8449; color: #fff; }
.dfspl-btn-success:hover { background: #27ae60; }
.dfspl-btn-info { background: #1a6fa5; color: #fff; }
.dfspl-btn-info:hover { background: #2196c4; }
.dfspl-btn-danger { background: #b03a2e; color: #fff; }
.dfspl-btn-danger:hover { background: #c0392b; }

/* Badge état de commande actif */
.dfspl-active-states {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 4px;
}

.dfspl-state-badge {
    background: #e8ecf3;
    color: #0f3460;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
}

/* Résultats */
.dfspl-results-panel {
    background: #fff;
    border: 1px solid #e0e4ea;
    border-radius: 8px;
    padding: 0;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    overflow: hidden;
}

.dfspl-results-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px;
    background: #f8f9fc;
    border-bottom: 1px solid #e0e4ea;
}

.dfspl-results-title { font-size: 13px; font-weight: 700; color: #333; margin: 0; }
.dfspl-results-count { font-size: 12px; color: #888; background: #e8ecf3; padding: 3px 10px; border-radius: 12px; }

.dfspl-table-wrapper { overflow-x: auto; }

.dfspl-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.dfspl-table thead tr { background: #1a1a2e; color: #fff; }
.dfspl-table thead th { padding: 12px 14px; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border: none; white-space: nowrap; }
.dfspl-table tbody tr { border-bottom: 1px solid #f0f0f0; transition: background 0.1s; }
.dfspl-table tbody tr:hover { background: #f0f5ff; }
.dfspl-table tbody tr:nth-child(even) { background: #fafafa; }
.dfspl-table tbody tr:nth-child(even):hover { background: #f0f5ff; }
.dfspl-table td { padding: 11px 14px; color: #333; vertical-align: middle; }

.dfspl-qty-badge {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 36px; height: 28px;
    background: #0f3460; color: #fff;
    border-radius: 14px; font-weight: 700; font-size: 13px; padding: 0 10px;
}

.dfspl-carrier-label { display: inline-flex; align-items: center; gap: 6px; font-weight: 500; }
.dfspl-carrier-cc { color: #0f3460; font-weight: 600; }
.dfspl-carrier-cc::before { content: '📍'; font-size: 12px; }

.dfspl-orders-list { font-family: monospace; font-size: 12px; color: #555; max-width: 300px; word-break: break-all; }
.dfspl-truncated-warning { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; color: #c0392b; font-style: italic; }

.dfspl-date-badge { background: #e8f5e9; color: #1e8449; padding: 3px 10px; border-radius: 12px; font-weight: 600; font-size: 12px; white-space: nowrap; }

/* États vide / placeholder */
.dfspl-empty-state { text-align: center; padding: 60px 20px; color: #aaa; }
.dfspl-empty-state .dfspl-empty-icon { font-size: 48px; margin-bottom: 12px; opacity: 0.4; }
.dfspl-empty-state p { font-size: 14px; margin: 0; }

.dfspl-placeholder { text-align: center; padding: 50px 20px; background: #f8f9fc; border: 2px dashed #d0d7e3; border-radius: 8px; color: #aaa; }
.dfspl-placeholder .dfspl-placeholder-icon { font-size: 40px; margin-bottom: 10px; opacity: 0.5; }
.dfspl-placeholder p { font-size: 13px; margin: 0; }
</style>

{* ============================================================
   EN-TÊTE
   ============================================================ *}
<div class="dfspl-header">
    <span class="dfspl-icon">📦</span>
    <div>
        <h1>DFS Picking List</h1>
        <p class="dfspl-subtitle">Maison Lorho — Cyrille Mohr · Digital Food System</p>
    </div>
</div>

{* ============================================================
   PANNEAU FILTRES
   ============================================================ *}
<div class="dfspl-filter-panel">
    <p class="panel-title">🔍 Filtres de recherche</p>

    <form method="get" action="index.php">
        <input type="hidden" name="controller" value="AdminDfsPickingList">
        <input type="hidden" name="token" value="{$token|escape:'html'}">
        <input type="hidden" name="submit_filter" value="1">

        {* ---- Ligne 1 : Mode de livraison + Dates ---- *}
        <p class="dfspl-filter-row-label">Mode &amp; Date</p>
        <div class="dfspl-form-row">

            <div class="dfspl-form-group" style="flex: 2; min-width: 240px;">
                <label for="picking_mode">Mode de livraison</label>
                <select name="picking_mode" id="picking_mode">
                    <option value="">— Tous les modes —</option>
                    {foreach from=$mode_options item=opt}
                        <option value="{$opt.key|escape:'html'}"
                            {if $filters.mode == $opt.key}selected="selected"{/if}>
                            {$opt.name|escape:'html'}
                        </option>
                    {/foreach}
                </select>
            </div>

            <div class="dfspl-form-group">
                <label for="picking_date_from">Date</label>
                <input type="date"
                    name="picking_date_from"
                    id="picking_date_from"
                    value="{$filters.date_from|escape:'html'}"
                    max="{$today|escape:'html'}">
            </div>

            <div class="dfspl-form-group">
                <label for="picking_date_to">au <span style="font-weight:400;color:#aaa;">(optionnel)</span></label>
                <input type="date"
                    name="picking_date_to"
                    id="picking_date_to"
                    value="{$filters.date_to|escape:'html'}"
                    max="{$today|escape:'html'}">
            </div>

        </div>

        {* ---- Séparateur ---- *}
        <div class="dfspl-filter-separator"></div>

        {* ---- Ligne 2 : État de commande + bouton ---- *}
        <p class="dfspl-filter-row-label" style="margin-top:12px;">État de commande</p>
        <div class="dfspl-form-row" style="align-items: flex-end;">

            <div class="dfspl-states-wrapper">
                <label for="picking_states">
                    État de la commande
                    <span class="dfspl-states-hint">— Par défaut : commandes en cours (Ctrl+clic pour multi-sélection)</span>
                </label>
                <select name="picking_states[]" id="picking_states" multiple>
                    {foreach from=$order_states item=state}
                        <option value="{$state.id_order_state|intval}"
                            {if in_array($state.id_order_state|intval, $filters.states)}selected="selected"{/if}>
                            {$state.name|escape:'html'}
                        </option>
                    {/foreach}
                </select>
            </div>

            {* Bouton Afficher aligné en bas *}
            <div class="dfspl-form-group">
                <label>&nbsp;</label>
                <button type="submit" name="submit_filter" class="dfspl-btn dfspl-btn-primary">
                    ▶ Afficher
                </button>
            </div>

        </div>

        {* ---- Note d'aide sous le multi-select ---- *}
        <p style="font-size:11px; color:#999; margin: 8px 0 0; font-style:italic;">
            💡 Aucune sélection d'état = commandes en cours uniquement.
            Sélectionnez un ou plusieurs états de commande pour filtrer précisément.<br>
            Si aucun mode de livraison ni date n'est sélectionné, l'affichage comportera la totalité des commandes en cours.
        </p>

        {* ---- Barre d'export (formulaires POST séparés du filtre GET) ---- *}
        {if $has_results}
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #eee; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <span style="font-size: 12px; color: #888; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px;">Exporter :</span>

                {* Export CSV *}
                <form method="post" action="{$controller_url|escape:'html'}" style="margin:0;">
                    <input type="hidden" name="picking_mode" value="{$filters.mode|escape:'html'}">
                    <input type="hidden" name="picking_date_from" value="{$filters.date_from|escape:'html'}">
                    <input type="hidden" name="picking_date_to" value="{$filters.date_to|escape:'html'}">
                    {foreach from=$filters.states item=sid}
                        <input type="hidden" name="picking_states[]" value="{$sid|intval}">
                    {/foreach}
                    <button type="submit" name="export_csv" value="1" class="dfspl-btn dfspl-btn-success">📄 CSV</button>
                </form>

                {* Export XLSX *}
                <form method="post" action="{$controller_url|escape:'html'}" style="margin:0;">
                    <input type="hidden" name="picking_mode" value="{$filters.mode|escape:'html'}">
                    <input type="hidden" name="picking_date_from" value="{$filters.date_from|escape:'html'}">
                    <input type="hidden" name="picking_date_to" value="{$filters.date_to|escape:'html'}">
                    {foreach from=$filters.states item=sid}
                        <input type="hidden" name="picking_states[]" value="{$sid|intval}">
                    {/foreach}
                    <button type="submit" name="export_xlsx" value="1" class="dfspl-btn dfspl-btn-info">📊 Excel (XLSX)</button>
                </form>

                {* Export PDF *}
                <form method="post" action="{$controller_url|escape:'html'}" style="margin:0;">
                    <input type="hidden" name="picking_mode" value="{$filters.mode|escape:'html'}">
                    <input type="hidden" name="picking_date_from" value="{$filters.date_from|escape:'html'}">
                    <input type="hidden" name="picking_date_to" value="{$filters.date_to|escape:'html'}">
                    {foreach from=$filters.states item=sid}
                        <input type="hidden" name="picking_states[]" value="{$sid|intval}">
                    {/foreach}
                    <button type="submit" name="export_pdf" value="1" class="dfspl-btn dfspl-btn-danger">📋 PDF</button>
                </form>

            </div>
        {/if}

    </form>
</div>

{* ============================================================
   RÉSULTATS
   ============================================================ *}

{if !$has_results}
    <div class="dfspl-results-panel">
        <div class="dfspl-empty-state">
            <div class="dfspl-empty-icon">📭</div>
            <p>Aucune commande trouvée pour ces critères.</p>
        </div>
    </div>

{else}
    <div class="dfspl-results-panel">

        <div class="dfspl-results-header">
            <span class="dfspl-results-title">
                📦 Résultats
                {if $filters.is_range}
                    · Du {$filters.date_from|escape:'html'} au {$filters.date_to|escape:'html'}
                {elseif $filters.date_from}
                    · {$filters.date_from|escape:'html'}
                {/if}
            </span>
            <span class="dfspl-results-count">{$rows|count} produit(s)</span>
        </div>

        <div class="dfspl-table-wrapper">
            <table class="dfspl-table">
                <thead>
                    <tr>
                        <th>Nom produit</th>
                        <th style="text-align:center;">Qté</th>
                        <th>Mode de livraison</th>
                        {if $show_date_col}<th>Date picking</th>{/if}
                        <th>Commandes</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$rows item=row}
                        <tr>
                            <td><strong>{$row.product_name|escape:'html'}</strong></td>

                            <td style="text-align:center;">
                                <span class="dfspl-qty-badge">{$row.total_qty}</span>
                            </td>

                            <td>
                                {if 'Retrait en boutique'|strpos:$row.carrier_label !== false}
                                    <span class="dfspl-carrier-label dfspl-carrier-cc">
                                        {$row.carrier_label|escape:'html'}
                                    </span>
                                {else}
                                    <span class="dfspl-carrier-label">
                                        🚚 {$row.carrier_label|escape:'html'}
                                    </span>
                                {/if}
                            </td>

                            {if $show_date_col}
                                <td>
                                    <span class="dfspl-date-badge">
                                        {$row.picking_date|date_format:'%d/%m/%Y'}
                                    </span>
                                </td>
                            {/if}

                            <td>
                                <span class="dfspl-orders-list">{$row.orders_list|escape:'html'}</span>
                                {if isset($row.orders_list_truncated) && $row.orders_list_truncated}
                                    <br>
                                    <span class="dfspl-truncated-warning">
                                        ⚠ Liste tronquée — contacter le support technique
                                    </span>
                                {/if}
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>

    </div>
{/if}
