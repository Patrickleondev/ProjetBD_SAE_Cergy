package fr.voltoise.serveur;

import java.io.FileInputStream;
import java.io.IOException;
import java.net.ServerSocket;
import java.net.Socket;
import java.time.LocalTime;
import java.time.format.DateTimeFormatter;
import java.util.Properties;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.atomic.AtomicInteger;

public class ServeurBornes {

    // au-dela, les connexions attendent qu une place se libere
    private static final int MAX_BORNES = 50;

    private static final AtomicInteger compteurDialogues = new AtomicInteger(1000);
    private static final DateTimeFormatter HEURE = DateTimeFormatter.ofPattern("HH:mm:ss");

    public static void main(String[] args) throws IOException {
        Properties config = new Properties();
        String fichier = args.length > 0 ? args[0] : "serveur.properties";
        try (FileInputStream in = new FileInputStream(fichier)) {
            config.load(in);
        }

        int port = Integer.parseInt(config.getProperty("port", "5000"));
        AccesBase.configurer(config.getProperty("db.url"),
                config.getProperty("db.user"),
                config.getProperty("db.password"));

        ExecutorService pool = Executors.newFixedThreadPool(MAX_BORNES);

        try (ServerSocket serveur = new ServerSocket(port)) {
            log("serveur en ecoute sur le port " + port);
            while (true) {
                Socket socket = serveur.accept();
                int numero = compteurDialogues.incrementAndGet();
                pool.submit(new DialogueBorne(socket, numero));
            }
        }
    }

    static synchronized void log(String message) {
        System.out.println("[" + LocalTime.now().format(HEURE) + "] " + message);
    }
}
