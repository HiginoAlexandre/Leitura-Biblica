import mysql.connector
from selenium import webdriver
from selenium.webdriver.common.by import By
import time

# Conectar ao banco de dados MySQL
db = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="leiturabiblica"
)

cursor = db.cursor()

# Selecionar os registros da tabela capitulo onde conteudo não é vazio
cursor.execute("SELECT id, conteudo FROM capitulo WHERE conteudo != ''")
capitulos = cursor.fetchall()

# Função para dividir o texto em blocos menores que 1500 caracteres
def dividir_texto(texto, limite=1500):
    paragrafos = texto.split("\n")
    blocos = []
    bloco_atual = ""

    for paragrafo in paragrafos:
        if len(bloco_atual + paragrafo) <= limite:
            bloco_atual += paragrafo + "\n"
        else:
            blocos.append(bloco_atual.strip())
            bloco_atual = paragrafo + "\n"

    if bloco_atual:
        blocos.append(bloco_atual.strip())

    return blocos

# Configurar o Selenium
driver = webdriver.Chrome()
driver.get("https://www.deepl.com/pt-PT/translator?utm_source=lingueecombr&utm_medium=linguee&utm_content=header_translator")

# Seletores CSS dos elementos do tradutor
input_selector = "#textareasContainer > div.rounded-es-inherit.relative.min-h-[240px].min-w-0.md\\:min-h-[clamp(250px,50vh,557px)].mobile\\:min-h-0.mobile\\:portrait\\:max-h-[calc((100vh-61px-1px-64px)/2)] > section > div > div.relative.flex-1.rounded-inherit.mobile\\:min-h-0 > d-textarea > div:nth-child(1) > p"
output_selector = "#textareasContainer > div.rounded-ee-inherit.relative.min-h-[240px].min-w-0.md\\:min-h-[clamp(250px,50vh,557px)].mobile\\:min-h-0.mobile\\:flex-1.mobile\\:portrait\\:max-h-[calc((100vh-61px-1px-64px)/2)].max-[768px]\\:min-h-[375px] > section > div.rounded-inherit.mobile\\:min-h-0.relative.flex.flex-1.flex-col > d-textarea > div > p"

# Processar cada capitulo
for capitulo in capitulos:
    id_capitulo, conteudo = capitulo
    blocos = dividir_texto(conteudo)
    conteudo_traduzido = ""

    for bloco in blocos:
        # Inserir texto no tradutor
        input_element = driver.find_element(By.CSS_SELECTOR, input_selector)
        input_element.clear()
        input_element.send_keys(bloco)

        # Aguardar a tradução ser gerada
        time.sleep(5)  # Ajuste conforme necessário

        # Obter a tradução
        output_element = driver.find_element(By.CSS_SELECTOR, output_selector)
        conteudo_traduzido += output_element.text + "\n"

    # Atualizar a coluna conteudo_pt no banco de dados
    cursor.execute("UPDATE capitulo SET conteudo_pt = %s WHERE id = %s", (conteudo_traduzido.strip(), id_capitulo))
    db.commit()

# Fechar conexões
driver.quit()
cursor.close()
db.close()