CREATE FUNCTION tr_rate_tables_clients_updater()
    RETURNS trigger
AS $$
BEGIN
    UPDATE clients SET orig_rate_table = NULL WHERE orig_rate_table = OLD.id;
    UPDATE clients SET term_rate_table = NULL WHERE term_rate_table = OLD.id;
    UPDATE accounts SET orig_rate_table = NULL WHERE orig_rate_table = OLD.id;
    UPDATE accounts SET term_rate_table = NULL WHERE term_rate_table = OLD.id;
RETURN NEW;
END;
$$
LANGUAGE 'plpgsql';

CREATE TRIGGER tr_rate_tables_before
    BEFORE UPDATE ON rate_tables
    FOR EACH ROW
    WHEN (OLD.id_companies <> NEW.id_companies)
    EXECUTE PROCEDURE tr_rate_tables_clients_updater();

----------------------

CREATE FUNCTION tr_clients_balances_updater()
    RETURNS trigger
AS $$
DECLARE
    currency_rate numeric;
BEGIN
    SELECT get_currency(OLD.id_currencies, NEW.id_currencies, NOW()) INTO currency_rate;
    NEW.credit = NEW.credit * currency_rate;
    UPDATE clients_balances SET balance = balance * currency_rate, balance_accountant = balance_accountant * currency_rate WHERE id_clients = OLD.id;
RETURN NEW;
END;
$$
LANGUAGE 'plpgsql';

CREATE TRIGGER clients_tr_balances_before
    BEFORE UPDATE ON clients
    FOR EACH ROW
    WHEN (OLD.id_currencies IS DISTINCT FROM NEW.id_currencies)
    EXECUTE PROCEDURE tr_clients_balances_updater();
