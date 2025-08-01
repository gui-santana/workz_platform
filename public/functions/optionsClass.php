<?php
function getCssClass($key, $itemCount) {
    // Classes padrão
    $cssClass = "cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h";

    // Adiciona classes específicas com base na posição
    if ($key === 0 && $itemCount === 1) {
        $cssClass .= " w-rounded-15"; // Primeiro e único item
    } else {
        if ($key === 0) {
            $cssClass .= " w-rounded-15-t"; // Primeiro item
        }
        if (($key + 1) === $itemCount) {
            $cssClass .= " w-rounded-15-b"; // Último item
        } else {
            $cssClass .= " border-b-input"; // Itens intermediários
        }
    }

    return $cssClass;
}
?>