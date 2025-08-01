<?
function randomPassword($tamanho){
    // Define os caracteres válidos para a senha
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()-_=+[]{}|;:,.<>?';
    
    // Gera um número aleatório de bytes
    $bytes = random_bytes(ceil($tamanho / 2));
    
    // Converte os bytes em uma string hexadecimal
    $senha = bin2hex($bytes);
    
    // Seleciona os caracteres da senha a partir da string de caracteres válidos
    $senha = str_shuffle($caracteres);
    
    // Retorna a senha com o tamanho especificado
    return substr($senha, 0, $tamanho);
}
?>
