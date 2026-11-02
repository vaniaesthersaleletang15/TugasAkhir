import { AlertTriangle } from "lucide-react";

export function LoadingState({ message = "Memuat data…" }) {
  return (
    <div className="state">
      <span className="spinner" />
      {message}
    </div>
  );
}

export function ErrorState({ message, retry }) {
  return (
    <div className="state error">
      <AlertTriangle size={20} />
      {message}
      {retry && (
        <button className="button secondary" onClick={retry}>
          Coba lagi
        </button>
      )}
    </div>
  );
}
