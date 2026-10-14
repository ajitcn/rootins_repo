# Pharmacy Orders Workspace

`/pharmacy/orders` is the canonical Pharmacy workspace for medicine orders, payment, and delivery operations.

The legacy `GET /pharmacy/delivery` page remains available as a compatibility redirect to Orders and carries a valid `delivery_status` filter when provided. Delivery data, transition services, permissions, audit events, and database records remain unchanged.

Order details expose scoped sections for order information, patient and prescription, medicines and stock, payment and invoice, delivery snapshots, and audit history. Payment and delivery updates continue to use their existing server-side transition contracts and permission checks.
