# Requirements Document

## Introduction

Esta especificação define os requisitos para exibir o Token de Acesso (API) no passo 4 do editor de aplicativos (app-builder.js). Atualmente, o campo existe mas está oculto, e precisa ser exibido quando o usuário está criando ou editando um aplicativo.

## Glossary

- **App Builder**: Interface web para criação e edição de aplicativos na plataforma Workz
- **Token de Acesso (API)**: Chave de autenticação gerada para permitir que aplicativos Flutter se comuniquem com a API da plataforma
- **Passo 4**: Etapa de configuração do aplicativo no fluxo do App Builder
- **token-field-container**: Elemento HTML que contém o campo do token de acesso

## Requirements

### Requirement 1

**User Story:** Como desenvolvedor criando um aplicativo, eu quero ver o token de acesso da API no passo 4, para que eu possa usar esse token na autenticação do meu aplicativo.

#### Acceptance Criteria

1. WHEN o usuário está no passo 4 do App Builder, THE token-field-container SHALL be visible
2. WHEN o aplicativo é do tipo Flutter, THE token field SHALL display the generated token value
3. WHEN o aplicativo é do tipo JavaScript, THE token field SHALL be visible but may show appropriate messaging
4. THE token field SHALL include a copy button for easy token copying

### Requirement 2

**User Story:** Como desenvolvedor editando um aplicativo existente, eu quero ver o token de acesso atual no passo 4, para que eu possa verificar ou copiar o token quando necessário.

#### Acceptance Criteria

1. WHEN o usuário está editando um aplicativo existente, THE token field SHALL display the current token value
2. WHEN o token está disponível, THE copy button SHALL be functional
3. THE token field SHALL be read-only to prevent accidental modification

### Requirement 3

**User Story:** Como usuário do App Builder, eu quero que o campo de token seja exibido de forma consistente, para que eu tenha uma experiência de usuário clara e previsível.

#### Acceptance Criteria

1. THE token field SHALL be visible by default in step 4
2. THE token field SHALL maintain consistent styling with other form elements
3. THE token field SHALL include appropriate help text explaining its purpose