# Como instalar a fonte Urbanist no dompdf

Para usar a fonte Urbanist nos PDFs gerados pelo sistema, você precisa seguir estes passos:

## Opção 1: Habilitar fontes remotas (Mais fácil, mas menos seguro)

1. Edite o arquivo `config/dompdf.php`
2. Altere `'enable_remote' => false,` para `'enable_remote' => true,`
3. A fonte Urbanist será carregada automaticamente do Google Fonts

⚠️ **Atenção**: Habilitar fontes remotas pode ser um risco de segurança.

## Opção 2: Instalar a fonte localmente (Recomendado)

1. Baixe os arquivos .ttf da fonte Urbanist:
   - Acesse: https://fonts.google.com/specimen/Urbanist
   - Baixe os arquivos .ttf necessários (Urbanist-Regular.ttf, Urbanist-Bold.ttf, etc.)

2. Coloque os arquivos na pasta: `storage/fonts/`

3. Use o script load_font.php do dompdf para carregar a fonte:
   ```bash
   php vendor/dompdf/dompdf/src/Dompdf/lib/fonts/load_font.php Urbanist storage/fonts/Urbanist-Regular.ttf
   php vendor/dompdf/dompdf/src/Dompdf/lib/fonts/load_font.php Urbanist-Bold storage/fonts/Urbanist-Bold.ttf
   ```

4. Verifique se os arquivos foram criados em `storage/fonts/` (deve aparecer Urbanist.afm e outros arquivos)

## Opção 3: Usar uma fonte alternativa (Temporário)

Se não conseguir instalar a Urbanist, o sistema usará "DejaVu Sans" como fallback, que é uma fonte padrão do dompdf.
