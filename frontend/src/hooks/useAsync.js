import { useCallback, useEffect, useState } from "react";

export default function useAsync(loader, dependencies = []) {
  const [state, setState] = useState({ loading: true, data: null, error: "" });
  const run = useCallback(async () => {
    setState((value) => ({ ...value, loading: true, error: "" }));
    try {
      const response = await loader();
      setState({ loading: false, data: response.data, error: "" });
    } catch (error) {
      setState({ loading: false, data: null, error: error.message });
    }
  }, dependencies);
  useEffect(() => {
    run();
  }, [run]);
  return { ...state, reload: run };
}
