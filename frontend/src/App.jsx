import { useEffect, useState } from "react";
import { Navigate, Route, Routes, useNavigate } from "react-router-dom";
import { api } from "./api";
import { LoadingState } from "./components/AsyncState";
import LoginPage from "./features/auth/LoginPage";
import AppShell from "./layouts/AppShell";
import {
  ForgotPasswordPage,
  RegisterPage,
  ResetPasswordPage,
  VerifyPage,
} from "./Modules";

export default function App() {
  const [auth, setAuth] = useState({ loading: true, user: null });
  const navigate = useNavigate();

  useEffect(() => {
    api
      .me()
      .then((response) => setAuth({ loading: false, user: response.data }))
      .catch(() => setAuth({ loading: false, user: null }));
  }, []);

  const handleLogin = (user) => {
    setAuth({ loading: false, user });
  };

  const logout = async () => {
    const loginPath =
      auth.user?.role === "pengurus" ? "/transportir/login" : "/internal/login";

    try {
      await api.logout();
    } finally {
      setAuth({ loading: false, user: null });
      navigate(loginPath);
    }
  };

  if (auth.loading) return <LoadingState />;
  if (auth.user) return <AppShell user={auth.user} onLogout={logout} />;

  return (
    <Routes>
      <Route
        path="/internal/login"
        element={<LoginPage portal="internal" onLogin={handleLogin} />}
      />
      <Route
        path="/transportir/login"
        element={<LoginPage portal="transportir" onLogin={handleLogin} />}
      />
      <Route
        path="/internal/register"
        element={<Navigate to="/internal/login" replace />}
      />
      <Route
        path="/transportir/register"
        element={<RegisterPage portal="transportir" />}
      />
      <Route
        path="/transportir/forgot-password"
        element={<ForgotPasswordPage />}
      />
      <Route
        path="/transportir/reset-password"
        element={<ResetPasswordPage />}
      />
      <Route
        path="/register"
        element={<Navigate to="/transportir/register" replace />}
      />
      <Route path="/verify/:value" element={<VerifyPage />} />
      <Route path="*" element={<Navigate to="/internal/login" replace />} />
    </Routes>
  );
}
