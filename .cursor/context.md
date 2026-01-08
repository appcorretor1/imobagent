Você está trabalhando em um projeto Laravel avançado, multi-tenant, 
com foco em automação comercial via WhatsApp para o mercado imobiliário.

Contexto geral do produto:
- Assistente inteligente para corretores e gestores imobiliários
- Operação principal ocorre via WhatsApp (inbound e outbound)
- O sistema entende mensagens, arquivos, imagens, PDFs e áudios
- Atua com múltiplos empreendimentos, corretores e empresas (tenants)

Tecnologias e integrações:
- Backend em Laravel (Controllers finos + Services bem definidos)
- WhatsApp integrado via Z-API
- Automação externa via Make.com (webhooks e cenários)
- AWS S3 para armazenamento de mídias, documentos e arquivos
- IA (OpenAI) para interpretação de mensagens, documentos, OCR e contexto
- Cache e controle de estado por telefone (state machine)

Funcionalidades principais do projeto:
- Fluxo inbound inteligente no WhatsApp, baseado em estado da conversa
- Criação, seleção e troca de empreendimentos via comandos no WhatsApp
- Upload automático de fotos, vídeos, PDFs e documentos por WhatsApp
- Organização de arquivos por tenant → empresa → empreendimento
- Treinamento da IA com dados do empreendimento
- Respostas automáticas baseadas em contexto, histórico e documentos
- Dashboard para gestores acompanharem uso, corretores e ativos
- Controle de permissões por empresa, corretor e empreendimento

Padrões e regras importantes:
- Sempre reutilize código existente antes de sugerir algo novo
- Não crie estruturas, tabelas ou services sem verificar se já existem
- Respeite o fluxo real de negócio (WhatsApp → Estado → Ação)
- Preserve logs existentes e padrão de observabilidade
- Considere impacto em multi-tenant e isolamento de dados
- Prefira evoluções incrementais a refatorações grandes
- Cite arquivos reais (paths corretos) ao sugerir código

Ao responder:
- Leia os arquivos relevantes do projeto antes de sugerir alterações
- Baseie decisões nas funcionalidades já implementadas nesta pasta
- Seja preciso, prático e compatível com a arquitetura atual
- Evite respostas genéricas ou exemplos fora do contexto do projeto
