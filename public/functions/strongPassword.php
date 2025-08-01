<?php
function isStrongPassword($password) {
    $minLength = 8;
    $containsUppercase = preg_match('/[A-Z]/', $password);
    $containsLowercase = preg_match('/[a-z]/', $password);
    $containsNumber = preg_match('/[0-9]/', $password);
    $containsSpecial = preg_match('/[\W_]/', $password); // \W para não alfanuméricos
    $containsSpace = preg_match('/\s/', $password);

    // Verifica se a senha atende a todos os critérios
    if (strlen($password) < $minLength) {
        return "A senha deve ter pelo menos $minLength caracteres.";
    }
    if (!$containsUppercase) {
        return "A senha deve conter pelo menos uma letra maiúscula.";
    }
    if (!$containsLowercase) {
        return "A senha deve conter pelo menos uma letra minúscula.";
    }
    if (!$containsNumber) {
        return "A senha deve conter pelo menos um número.";
    }
    if (!$containsSpecial) {
        return "A senha deve conter pelo menos um caractere especial.";
    }
    if ($containsSpace) {
        return "A senha não deve conter espaços em branco.";
    }

    return true; // Senha válida
}
?>