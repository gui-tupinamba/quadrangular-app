import { useEffect, useState, type FormEvent } from "react";
import { ArrowLeft, CheckCircle2, ShieldCheck } from "lucide-react";
import { submitPublicAccountDeletionRequest } from "../api";
import { brand } from "../branding";

const contact = "contatoguitupinamba@gmail.com";

export function PublicInformation({ deletion = false }: { deletion?: boolean }) {
  useEffect(() => {
    document.title = deletion ? "Solicitar exclusão de conta · IEQ - Cinturão Verde" : "Política de privacidade · IEQ - Cinturão Verde";
  }, [deletion]);
  return (
    <main className="public-page">
      <header className="public-header">
        <div className="brand">
          <span className="brand-mark"><img src={brand.assets.logo} alt="" /></span>
          <span>{brand.name}<small>{brand.tagline}</small></span>
        </div>
        <a href="/privacidade">Política de privacidade</a>
      </header>
      <article className="public-document">
        <a className="public-back" href="/">
          <ArrowLeft size={17} /> Voltar ao aplicativo
        </a>
        {deletion ? <PublicDeletionForm /> : <PrivacyPolicy />}
      </article>
      <footer className="public-footer">
        <a href="/privacidade">Privacidade</a>
        <a href="/excluir-conta">Solicitar exclusão de conta</a>
      </footer>
    </main>
  );
}

function PrivacyPolicy() {
  return (
    <>
      <span className="public-eyebrow">IEQ - CINTURÃO VERDE</span>
      <h1>Política de privacidade</h1>
      <p className="public-lead">
        Esta política explica como a Igreja do Evangelho Quadrangular do
        Cinturão Verde trata dados pessoais usados no aplicativo e na plataforma
        web.
      </p>
      <p><strong>Atualizada em 4 de novembro de 2026.</strong></p>

      <h2>Quem é responsável e como falar conosco</h2>
      <p>
        A Igreja do Evangelho Quadrangular do Cinturão Verde, em Boa Vista,
        Roraima, Brasil, é responsável pelo tratamento descrito aqui. Para
        assuntos de privacidade, o canal de contato disponibilizado é{" "}
        <a href={`mailto:${contact}`}>{contact}</a>. Este endereço é informado
        como canal de contato, sem declaração de que seja um endereço
        institucional.
      </p>

      <h2>Dados tratados</h2>
      <ul>
        <li>
          Cadastro e perfil: nome, e-mail, telefone e data de nascimento quando
          preenchidos.
        </li>
        <li>
          Participação: ministérios, situação da participação e liderança
          específica.
        </li>
        <li>
          Uso comunitário: publicações, comentários, eventos, atividades,
          agendas e interações com esses conteúdos.
        </li>
        <li>
          Conta e segurança: sessões, horários de acesso, tokens de renovação
          armazenados como resumo criptográfico, limites de tentativas e
          registros de auditoria das operações administrativas.
        </li>
        <li>
          Notificações: preferências, identificador de dispositivo para push,
          plataforma, sessão relacionada, avisos recebidos e estado de leitura.
        </li>
        <li>
          Solicitação de exclusão: nome, e-mail, informação adicional opcional,
          identificador do pedido e dados mínimos de processamento. Não envie
          senha ou documentos pessoais pelo formulário.
        </li>
      </ul>

      <h2>Para que usamos os dados</h2>
      <p>
        Usamos os dados para autenticar e proteger contas, administrar perfis e
        ministérios, controlar o acesso a conteúdo conforme participação e
        permissões, publicar informações comunitárias, entregar notificações
        escolhidas e processar solicitações de titulares. Registros técnicos e
        administrativos apoiam a segurança, a prevenção de abuso e a
        investigação de operações relevantes.
      </p>

      <h2>Compartilhamento e serviços envolvidos</h2>
      <p>
        A aplicação usa Cloudflare para entrega e proteção do acesso externo.
        Quando notificações push estão habilitadas, tokens e dados técnicos
        necessários à entrega passam pelos serviços Expo Push e, no Android,
        Firebase Cloud Messaging. O conteúdo mostrado na tela bloqueada é
        genérico; o aplicativo consulta a API para obter o conteúdo autorizado.
        Não afirmamos usar publicidade comportamental ou analytics, pois isso
        não está implementado no código revisado.
      </p>

      <h2>Armazenamento e segurança</h2>
      <p>
        A API usa Symfony, Doctrine e PostgreSQL. O acesso externo é feito por
        HTTPS; sessões podem ser revogadas e tokens de renovação não são
        armazenados em texto puro. A aplicação aplica controle de acesso no
        servidor, limita tentativas de autenticação e mantém cópias de
        segurança operacionais. Nenhuma medida elimina todos os riscos; o
        acesso é limitado às pessoas e aos serviços que precisam operar a
        plataforma.
      </p>

      <h2>Retenção</h2>
      <p>
        Dados de perfil são mantidos enquanto a conta estiver ativa. Conteúdos
        comunitários podem permanecer após a exclusão com autoria anonimizada;
        comentários são removidos. Ao concluir ou rejeitar um pedido de
        exclusão, os dados de contato e a informação adicional do pedido são
        apagados, mantendo-se o identificador, o estado, as datas e os registros
        mínimos necessários ao histórico de processamento. A aplicação não
        configura atualmente expurgo automático nem um prazo geral de retenção
        para sessões expiradas e registros de auditoria; esses prazos ainda não
        estão definidos. Cópias de segurança podem conter
        dados excluídos até sua rotação: a retenção documentada é de 14 dias
        para cópias locais e 30 dias para cópias externas, conforme a
        configuração operacional.
      </p>

      <h2>Seus direitos e como solicitar</h2>
      <p>
        Nos termos da LGPD e conforme as hipóteses aplicáveis, você pode
        solicitar confirmação e acesso, correção, anonimização, bloqueio ou
        eliminação de dados desnecessários ou tratados em desconformidade,
        portabilidade quando regulamentada e aplicável, informações sobre
        compartilhamento e revogação de consentimento quando essa for a base
        utilizada. Consulte os <a href="https://www.gov.br/anpd/pt-br/assuntos/titular-de-dados-1/direito-dos-titulares" target="_blank" rel="noreferrer">direitos dos titulares explicados pela ANPD</a>.
        Para pedir a exclusão sem entrar no aplicativo, use a página
        <a href="/excluir-conta"> Solicitar exclusão de conta</a>. A equipe
        validará a identidade manualmente antes de processar a solicitação. O
        formulário não solicita senha e sua resposta não revela se um e-mail
        está cadastrado.
      </p>
      <p>
        Publicações, eventos e atividades podem permanecer sem identificação do
        autor; comentários são removidos. Sessões, tokens de renovação,
        dispositivos, preferências e vínculos com ministérios são removidos ou
        invalidados no processamento. Uma conta ADMIN ativa não pode ser
        excluída se isso deixar o sistema sem outro ADMIN ativo.
      </p>

      <p className="public-updated">Última atualização: 4 de novembro de 2026.</p>
    </>
  );
}

function PublicDeletionForm() {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [receipt, setReceipt] = useState<{ message: string; reference: string } | null>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const data = new FormData(form);
    setBusy(true);
    setError("");
    try {
      const result = await submitPublicAccountDeletionRequest({
        name: String(data.get("name") || ""),
        email: String(data.get("email") || ""),
        confirm: data.get("confirm") === "on",
        additional_information: String(data.get("additional_information") || ""),
        website: String(data.get("website") || ""),
      });
      setReceipt(result);
      form.reset();
    } catch (failure) {
      setError(failure instanceof Error ? failure.message : "Não foi possível enviar. Tente novamente.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <>
      <span className="public-eyebrow">CONTROLE DA SUA CONTA</span>
      <h1>Solicitar exclusão de conta</h1>
      <p className="public-lead">
        Use este formulário mesmo que não consiga entrar no aplicativo IEQ -
        Cinturão Verde. A solicitação ficará pendente até a equipe validar sua
        identidade por contato fora do sistema.
      </p>
      <section className="public-callout">
        <ShieldCheck size={20} />
        <p>
          O envio não exclui a conta imediatamente. Não informe senha ou envie
          documentos neste formulário. A resposta não confirma se o e-mail
          informado pertence a uma conta.
        </p>
      </section>
      {receipt ? (
        <section className="public-receipt" role="status" aria-live="polite">
          <CheckCircle2 size={30} />
          <h2>Solicitação recebida</h2>
          <p>{receipt.message}</p>
          <p>Guarde este identificador para referência:</p>
          <code>{receipt.reference}</code>
          <button className="primary" onClick={() => setReceipt(null)}>Enviar outra solicitação</button>
        </section>
      ) : (
        <form className="public-form" onSubmit={submit}>
          {error && <div className="error" role="alert">{error}</div>}
          <label>
            Nome completo
            <input name="name" autoComplete="name" maxLength={120} required />
          </label>
          <label>
            E-mail cadastrado
            <input name="email" type="email" autoComplete="email" maxLength={180} required />
          </label>
          <label>
            Informações adicionais <span>(opcional)</span>
            <textarea name="additional_information" rows={4} maxLength={2000} />
          </label>
          <label className="public-check">
            <input name="confirm" type="checkbox" required />
            <span>Confirmo que desejo solicitar a exclusão da conta associada aos dados informados.</span>
          </label>
          <div className="public-honeypot" aria-hidden="true">
            <label>Deixe este campo vazio<input name="website" tabIndex={-1} autoComplete="off" /></label>
          </div>
          <button className="primary" disabled={busy}>
            {busy ? "Enviando…" : "Enviar solicitação"}
          </button>
          <p className="public-form-note">
            A validação é manual. Após validar a identidade, a equipe processará
            o pedido e informará o prazo estimado para este caso, pois não há um
            prazo único definido.
          </p>
        </form>
      )}
      <p className="public-contact">
        Dúvidas sobre privacidade? <a href={`mailto:${contact}`}>{contact}</a>
      </p>
    </>
  );
}
