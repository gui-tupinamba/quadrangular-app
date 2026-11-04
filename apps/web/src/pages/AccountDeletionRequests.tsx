import { useState } from "react";
import { Check, Eye, RotateCcw, ShieldCheck, X } from "lucide-react";
import { api } from "../api";
import { useSession } from "../session";
import { Badge, date, Empty, ErrorBox, Heading, Loading, Pager, useData } from "../ui";

type RequestItem = {
  reference: string;
  name: string | null;
  email: string | null;
  status: string;
  requested_at: string;
  identity_verified_at: string | null;
  processed_at: string | null;
  rejection_reason: string | null;
};
type RequestDetail = RequestItem & {
  additional_information: string | null;
  identity_verified_by_id: number | null;
  processed_by_id: number | null;
  history: { action: string; actor_id: number | null; created_at: string }[];
};

export function AccountDeletionRequestsPage() {
  const { permissions } = useSession();
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState("");
  const [selected, setSelected] = useState<string | null>(null);
  const list = useData<{ items: RequestItem[]; pagination: { page: number; limit: number; total: number } }>(
    `/admin/account-deletion-requests?page=${page}&limit=20${status ? `&status=${status}` : ""}`,
  );
  if (!permissions.manage_users) return <Empty title="Acesso não permitido" />;
  return (
    <>
      <Heading title="Solicitações de exclusão" subtitle="Valide a identidade e processe os pedidos com o histórico administrativo." />
      <div className="filters">
        <label>Estado
          <select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); }}>
            <option value="">Todos</option>
            {[["PENDING", "Pendente"], ["UNDER_REVIEW", "Em análise"], ["APPROVED", "Aprovada"], ["REJECTED", "Rejeitada"], ["COMPLETED", "Concluída"]].map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </select>
        </label>
      </div>
      <ErrorBox error={list.error} />
      {list.loading ? <Loading /> : list.data?.items.length ? (
        <div className="panel table-wrap">
          <table>
            <thead><tr><th>Identificador</th><th>Solicitante</th><th>Recebida</th><th>Estado</th><th>Ação</th></tr></thead>
            <tbody>{list.data.items.map((item) => (
              <tr key={item.reference}>
                <td><code>{item.reference}</code></td>
                <td><strong>{item.name || "Dados removidos"}</strong><small>{item.email || ""}</small></td>
                <td>{date(item.requested_at)}</td>
                <td><Badge value={item.status} /></td>
                <td><button onClick={() => setSelected(item.reference)}><Eye size={16} /> Abrir</button></td>
              </tr>
            ))}</tbody>
          </table>
        </div>
      ) : !list.error ? <Empty title="Nenhuma solicitação" /> : null}
      {list.data && <Pager pagination={list.data.pagination} onPage={setPage} />}
      {selected && <RequestReview reference={selected} close={() => setSelected(null)} refresh={list.reload} />}
    </>
  );
}

function RequestReview({ reference, close, refresh }: { reference: string; close: () => void; refresh: () => void }) {
  const detail = useData<{ request: RequestDetail }>(`/admin/account-deletion-requests/${reference}`);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<unknown>();
  const [identityChecked, setIdentityChecked] = useState(false);
  const [completeChecked, setCompleteChecked] = useState(false);
  const [reason, setReason] = useState("");
  const item = detail.data?.request;
  async function action(suffix: string, body?: object) {
    setBusy(true); setError(undefined);
    try {
      await api(`/admin/account-deletion-requests/${reference}/${suffix}`, "POST", body);
      detail.reload(); refresh();
      setIdentityChecked(false); setCompleteChecked(false);
    } catch (failure) { setError(failure); }
    finally { setBusy(false); }
  }
  return (
    <div className="modal-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) close(); }}>
      <section className="modal deletion-review" role="dialog" aria-modal="true" aria-labelledby="request-title">
        <header><h2 id="request-title">Solicitação</h2><button className="icon-button" onClick={close} aria-label="Fechar"><X /></button></header>
        <ErrorBox error={detail.error || error} />
        {detail.loading || !item ? <Loading /> : <>
          <dl className="request-facts">
            <div><dt>Identificador</dt><dd><code>{item.reference}</code></dd></div>
            <div><dt>Nome</dt><dd>{item.name || "Dados removidos"}</dd></div>
            <div><dt>E-mail</dt><dd>{item.email || "Dados removidos"}</dd></div>
            <div><dt>Recebida</dt><dd>{date(item.requested_at)}</dd></div>
            <div><dt>Estado</dt><dd><Badge value={item.status} /></dd></div>
            <div><dt>Validação de identidade</dt><dd>{item.identity_verified_at ? `Registrada em ${date(item.identity_verified_at)}` : "Pendente"}</dd></div>
            {item.additional_information && <div className="request-extra"><dt>Informações adicionais</dt><dd>{item.additional_information}</dd></div>}
            {item.rejection_reason && <div className="request-extra"><dt>Justificativa da rejeição</dt><dd>{item.rejection_reason}</dd></div>}
            <div className="request-extra"><dt>Histórico</dt><dd><ol>{item.history.map((entry, index) => <li key={`${entry.action}-${index}`}>{entry.action} · {date(entry.created_at)}{entry.actor_id ? ` · responsável ${entry.actor_id}` : ""}</li>)}</ol></dd></div>
          </dl>
          <p className="request-privacy-note">Confirme a identidade por contato fora do sistema. Não registre documentos nem informações sensíveis nas observações.</p>
          {item.status === "PENDING" && <button className="primary" disabled={busy} onClick={() => action("review")}><RotateCcw size={17} /> Iniciar análise</button>}
          {item.status === "UNDER_REVIEW" && !item.identity_verified_at && <>
            <label className="public-check"><input type="checkbox" checked={identityChecked} onChange={(event) => setIdentityChecked(event.target.checked)} /><span>Confirmo que validei a identidade por contato fora do sistema.</span></label>
            <button className="primary" disabled={busy || !identityChecked} onClick={() => action("verify-identity")}><ShieldCheck size={17} /> Registrar validação</button>
          </>}
          {item.status === "UNDER_REVIEW" && item.identity_verified_at && <button className="primary" disabled={busy} onClick={() => action("approve")}><Check size={17} /> Aprovar solicitação</button>}
          {item.status === "APPROVED" && <>
            <p>Ao concluir, comentários, sessões, tokens, dispositivos, preferências e vínculos serão removidos; autoria de publicações, eventos e atividades ficará como “Conta excluída”.</p>
            <label className="public-check"><input type="checkbox" checked={completeChecked} onChange={(event) => setCompleteChecked(event.target.checked)} /><span>Confirmo que a identidade foi validada e que a exclusão pode ser concluída.</span></label>
            <button className="primary" disabled={busy || !completeChecked} onClick={() => action("complete")}><Check size={17} /> Concluir exclusão</button>
          </>}
          {!["REJECTED", "COMPLETED"].includes(item.status) && <form className="reject-form" onSubmit={(event) => { event.preventDefault(); action("reject", { reason }); }}>
            <label>Justificativa para rejeitar<textarea value={reason} onChange={(event) => setReason(event.target.value)} maxLength={1000} rows={3} /></label>
            <button disabled={busy || reason.trim().length < 3}><X size={16} /> Rejeitar</button>
          </form>}
        </>}
      </section>
    </div>
  );
}
