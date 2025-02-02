<?php
if (!defined('ABSPATH')) exit;
?>

<div class="geralinks-container">
    <form id="geralinks-form" class="geralinks-form">
        <div>
            <input type="url" id="url-original" class="geralinks-input" placeholder="Cole aqui a URL longa" required>
        </div>
        <div>
            <input type="text" id="url-slug" class="geralinks-input" placeholder="Slug personalizado (opcional)">
        </div>
        <div>
            <button type="submit" class="geralinks-button">Gerar URL Curta</button>
        </div>
    </form>
    <div class="geralinks-resultado"></div>
</div>
