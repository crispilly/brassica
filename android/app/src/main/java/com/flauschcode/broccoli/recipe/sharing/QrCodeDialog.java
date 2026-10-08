package com.flauschcode.broccoli.recipe.sharing;

import android.app.Activity;
import android.graphics.Bitmap;
import android.graphics.Color;
import android.widget.ImageView;
import android.widget.Toast;

import androidx.appcompat.app.AlertDialog;

import com.flauschcode.broccoli.R;
import com.google.zxing.BarcodeFormat;
import com.google.zxing.MultiFormatWriter;
import com.google.zxing.WriterException;
import com.google.zxing.common.BitMatrix;

public final class QrCodeDialog {

    private QrCodeDialog() {
    }

    public static void show(Activity activity, String title, String value) {
        try {
            int size = Math.round(280 * activity.getResources().getDisplayMetrics().density);
            BitMatrix matrix = new MultiFormatWriter().encode(value, BarcodeFormat.QR_CODE, size, size);
            Bitmap bitmap = Bitmap.createBitmap(size, size, Bitmap.Config.ARGB_8888);

            for (int y = 0; y < size; y++) {
                for (int x = 0; x < size; x++) {
                    bitmap.setPixel(x, y, matrix.get(x, y) ? Color.BLACK : Color.WHITE);
                }
            }

            ImageView image = new ImageView(activity);
            int padding = Math.round(16 * activity.getResources().getDisplayMetrics().density);
            image.setPadding(padding, padding, padding, padding);
            image.setImageBitmap(bitmap);
            image.setContentDescription(activity.getString(R.string.qr_code_accessibility));

            new AlertDialog.Builder(activity)
                    .setTitle(title)
                    .setView(image)
                    .setPositiveButton(android.R.string.ok, null)
                    .show();
        } catch (WriterException e) {
            Toast.makeText(activity, R.string.qr_code_failed, Toast.LENGTH_LONG).show();
        }
    }
}
