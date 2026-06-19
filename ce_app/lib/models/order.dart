// lib/models/order.dart

class Order {
  final String  orderNumber;
  final String  status;
  final String  statusLabel;
  final String  productName;
  final int     quantity;
  final double  totalAmount;
  final double  patientCopay;
  final String? patientName;
  final String? trackingNumber;
  final String? estimatedDelivery;
  final String  createdAt;

  const Order({
    required this.orderNumber,
    required this.status,
    required this.statusLabel,
    required this.productName,
    required this.quantity,
    required this.totalAmount,
    required this.patientCopay,
    this.patientName,
    this.trackingNumber,
    this.estimatedDelivery,
    required this.createdAt,
  });

  factory Order.fromJson(Map<String, dynamic> json) => Order(
        orderNumber:       json['order_number'] as String,
        status:            json['status']       as String,
        statusLabel:       json['status_label'] as String,
        productName:       json['product_name'] as String,
        quantity:          (json['quantity'] as num).toInt(),
        totalAmount:       (json['total_amount']   as num).toDouble(),
        patientCopay:      (json['patient_copay']  as num).toDouble(),
        patientName:       json['patient_name']    as String?,
        trackingNumber:    json['tracking_number'] as String?,
        estimatedDelivery: json['estimated_delivery'] as String?,
        createdAt:         json['created_at']  as String,
      );
}
